<?php

namespace App\Filament\Kinesiologa\Pages;

use App\Models\Paciente;
use App\Models\User;
use App\Models\Consulta;
use App\Models\DiagnosticoFuncional;
use App\Models\EvaluacionFuncional;
use App\Models\EvaluacionRom;
use App\Models\MetodoRom;
use App\Models\Movimiento;
use App\Models\PacientePadecimiento;
use App\Models\PadecimientoDiagnostico;
use App\Models\Turno;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EvaluacionInicial extends Page
{
    // =========================
    // Wizard lógico (sin UI)
    // =========================
    public const PASO_DATOS       = 1;
    public const PASO_ANAMNESIS   = 2;
    public const PASO_EVALUACION  = 3;
    public const PASO_DIAGNOSTICO = 4;
    public const PASO_CIERRE      = 5;

    /** Paso actualmente visible en UI */
    public int $pasoActual = self::PASO_DATOS;

    /** ✅ MODO PRO: paso máximo “desbloqueado” (según consultas.paso_actual) */
    public int $pasoMaximo = self::PASO_DATOS;

    // =========================
    // Filament config
    // =========================
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Turnos y Consultas';
    protected static ?string $title           = 'Evaluación Inicial';
    protected static ?string $navigationLabel = 'Evaluación Inicial';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'evaluacion-inicial/{turno}';
    protected static string $view = 'filament.kinesiologa.pages.evaluacion-inicial';

    // =========================
    // State
    // =========================
    public Turno $turno;

    /** Resumen general (MVP) */
    public string $resumen = '';

    /**
     * Si true: deshabilitar edición/guardar en la vista
     * ✅ Depende de poder editar ANAMNESIS (no de ROM)
     */
    public bool $soloLectura = false;

    /** Repeater ROM */
    public array $roms = [];

    /** Errors por fila (para pintar en rojo en UI) */
    public array $romErrors = [];

    /** Options para selects */
    public array $movimientosOptions = [];
    public array $metodosOptions = [];

    /** Meta de movimientos (rangos normales) */
    public array $movimientosMeta = []; // [id_mov => ['min'=>?, 'max'=>?]]

    /** Histórico ROM cacheado */
    public array $romHistory = []; // ["movId|lado" => [ ['fecha'=>..., 'valor'=>..., 'metodo'=>...], ... ]]

    public ?int $pacienteUserId = null;   // users.id (FK de consultas)
    public ?int $pacientePerfilId = null; // pacientes.paciente_id (FK de paciente_padecimiento)

    /** Para excluir el “actual” del histórico */
    public ?int $consultaIdActual = null;

    /** Avisos del wizard (UI, no persistido) */
    public ?string $avisoWizard = null;

    // =========================
    // PASO 2 — Anamnesis
    // =========================
    public string $motivoConsulta = '';
    public ?int $evaDolor = null;
    public ?string $limitacionFuncional = null;

    public array $limitacionOptions = [
        'leve'     => 'Leve',
        'moderada' => 'Moderada',
        'severa'   => 'Severa',
        'otra'     => 'Otra',
    ];

    // =========================
    // PASO 4 — Diagnóstico
    // =========================
    public ?int $diagnosticoFuncionalId = null;
    public string $diagnosticoNotas = '';
    public array $diagnosticosOptions = [];

    /** Padecimiento “de esta consulta” (Opción A) */
    public ?int $padecimientoIdActual = null;

    // ✅ NUEVO: para mostrar en Blade
    public ?int $padecimientoTipoIdActual = null;
    public ?string $padecimientoTipoNombreActual = null;
    public ?string $padecimientoNombreActual = null;

    public function mount(Turno $turno): void
    {
        $this->turno = $turno->load([
            'paciente:id,name',
            'pacientePerfil:paciente_id,nombre,user_id',
            'consultorio:id_consultorio,nombre',
        ]);

        // Seguridad: solo dueña del turno
        $userId = (int) Auth::id();
        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            abort(403, 'No tenés permiso para acceder a este turno.');
        }

        $this->pacienteUserId   = $this->resolverPacienteUserId($this->turno);
        $this->pacientePerfilId = $this->resolverPacientePerfilId();
        $perfilId = $this->pacientePerfilId;

        // Catálogos ROM
        $movs = Movimiento::query()
            ->with('zona:id_zona_anatomica,nombre')
            ->where('activo', true)
            ->orderBy('id_zona_anatomica')
            ->orderBy('nombre')
            ->get();

        $this->movimientosOptions = $movs
            ->mapWithKeys(function (Movimiento $m) {
                $zona = $m->zona?->nombre ? ($m->zona->nombre . ' • ') : '';
                return [$m->id_movimiento => $zona . $m->nombre];
            })
            ->all();

        $this->movimientosMeta = $movs
            ->mapWithKeys(fn(Movimiento $m) => [
                $m->id_movimiento => [
                    'min' => $m->rango_norm_min,
                    'max' => $m->rango_norm_max,
                ],
            ])
            ->all();

        $this->metodosOptions = MetodoRom::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id_metodo')
            ->all();

        // Catálogo Diagnósticos (PASO 4)
        $this->diagnosticosOptions = DiagnosticoFuncional::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id_diagnostico_funcional')
            ->all();

        // =========================
        // Cargar consulta + wizard paso
        // =========================
        $consulta = Consulta::query()
            ->where('turno_id', $this->turno->id_turno)
            ->latest('id_consulta')
            ->first();

        if ($consulta) {
            $this->consultaIdActual = (int) $consulta->id_consulta;

            // ✅ A.2: si está finalizada, forzar modo cierre
            if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                $this->soloLectura = true;

                // Forzamos wizard en cierre como estado estable
                $this->pasoMaximo = self::PASO_CIERRE;
                $this->pasoActual = self::PASO_CIERRE;
            } else {
                // DB como fuente única (borrador)
                $this->pasoMaximo = (int) ($consulta->paso_actual ?? self::PASO_DATOS);
                $this->pasoActual = $this->pasoMaximo;
            }

            $evalFunc = EvaluacionFuncional::query()
                ->where('id_consulta', $consulta->id_consulta)
                ->first();

            $this->resumen = (string) ($consulta->resumen ?? '');

            if ($evalFunc) {
                $this->evaDolor = $evalFunc->eva_dolor !== null ? (int) $evalFunc->eva_dolor : null;
                $this->motivoConsulta = (string) ($evalFunc->motivo_consulta ?? '');
                $this->limitacionFuncional = $evalFunc->limitacion_funcional ?? null;

                $this->roms = EvaluacionRom::query()
                    ->where('id_eval_func', $evalFunc->id_eval_func)
                    ->orderBy('id_eval_rom')
                    ->get()
                    ->map(fn(EvaluacionRom $r) => [
                        'id_movimiento' => $r->id_movimiento,
                        'id_metodo'     => $r->id_metodo,
                        'lado'          => $r->lado,
                        'valor_grados'  => $r->valor_grados,
                        'observaciones' => $r->observaciones,
                    ])
                    ->all();
            }

            // =========================
            // PASO 4: cargar padecimiento + diagnóstico principal si existía
            // =========================
            $p = PacientePadecimiento::query()
                ->where('paciente_id', $perfilId)
                ->where('id_consulta', $consulta->id_consulta)
                ->first();

            if ($p) {
                // ✅ NUEVO: hidrata datos para UI (id, nombre, tipo...)
                $this->hidratarPadecimientoUI($p);

                $principal = PadecimientoDiagnostico::query()
                    ->where('id_padecimiento', $p->id_padecimiento)
                    ->where('es_principal', true)
                    ->orderByDesc('vigente_desde')
                    ->first();

                if ($principal) {
                    $this->diagnosticoFuncionalId = (int) $principal->id_diagnostico_funcional;
                    $this->diagnosticoNotas = (string) ($principal->notas ?? '');
                }
            }
        } else {
            $this->pasoActual = self::PASO_DATOS;
            $this->pasoMaximo = self::PASO_DATOS;
            $this->consultaIdActual = null;
        }

        if (empty($this->roms)) {
            $this->roms = [[
                'id_movimiento' => null,
                'id_metodo'     => null,
                'lado'          => 'der',
                'valor_grados'  => null,
                'observaciones' => null,
            ]];
        }

        $this->romErrors = array_fill(0, count($this->roms), null);

        // ✅ Solo lectura depende de poder editar ANAMNESIS
        $this->soloLectura = ! $this->puedeEditarAnamnesis();

        // ✅ Si el wizard ya fue forzado a cierre por finalizada, mantenelo
        if ($consulta && (string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
            $this->consultaIdActual = (int) $consulta->id_consulta;
            $this->soloLectura = true;
            $this->pasoMaximo = self::PASO_CIERRE;
            $this->pasoActual = self::PASO_CIERRE;

            $this->cargarHistorialRom();
            return;
        }

        $this->recalcularErroresRom();
        $this->cargarHistorialRom();
    }

    // =========================================================
    // PERMISOS (separados)
    // =========================================================

    /**
     * ✅ Permite editar "general" (Anamnesis/Diagnóstico) DURANTE el turno:
     * - turno no cancelado/no asistió
     * - consulta NO finalizada (si existe)
     */
    private function puedeEditarAnamnesis(): bool
    {
        $estado = (string) $this->turno->estado;

        if (in_array($estado, [
            Turno::ESTADO_CANCELADO,
            Turno::ESTADO_CANCELADO_TARDE,
            Turno::ESTADO_NO_ASISTIO,
        ], true)) {
            return false;
        }

        if ($this->consultaIdActual) {
            $consulta = Consulta::find($this->consultaIdActual);
            if ($consulta && (string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                return false;
            }
        }

        return true;
    }

    /**
     * ✅ Permite editar/guardar ROM SOLO cuando el turno terminó
     */
    private function puedeEditarEvaluacionRom(): bool
    {
        return $this->puedeEditar();
    }

    private function resolverPacientePerfilId(): int
    {
        // 1) Si el turno ya tiene el perfil (lo ideal)
        if (! empty($this->turno->paciente_perfil_id)) {
            return (int) $this->turno->paciente_perfil_id;
        }

        // 2) Fallback: buscar perfil por user_id (legacy)
        $perfilId = DB::table('pacientes')
            ->where('user_id', (int) $this->turno->paciente_id)
            ->value('paciente_id');

        if (! $perfilId) {
            throw new RuntimeException(
                'Este turno no tiene paciente_perfil_id y no existe un perfil en "pacientes" para este user. ' .
                    'Creá el perfil del paciente o completá turno.paciente_perfil_id.'
            );
        }

        return (int) $perfilId;
    }


    // ✅ Propiedad computada para usar en Blade como: $this->puedeEditarRom
    public function getPuedeEditarRomProperty(): bool
    {
        return $this->puedeEditarEvaluacionRom();
    }

    /**
     * (Lógica original) -> usar SOLO para ROM/Cierre.
     */
    private function puedeEditar(): bool
    {
        $estado = (string) $this->turno->estado;

        if (in_array($estado, [Turno::ESTADO_CANCELADO, Turno::ESTADO_CANCELADO_TARDE, Turno::ESTADO_NO_ASISTIO], true)) {
            return false;
        }

        if (! $this->turno->fin) {
            return false;
        }

        if (now()->lt($this->turno->fin)) {
            return false;
        }

        return true;
    }

    // =========================================================
    // HELPERS — Fuente única de verdad (flujo)
    // =========================================================

    private function resolverPacienteUserId(Turno $turno): int
    {
        // 1) Si paciente_id ya es un users.id válido
        if (! empty($turno->paciente_id) && User::query()->whereKey((int) $turno->paciente_id)->exists()) {
            return (int) $turno->paciente_id;
        }

        // 2) Si tengo paciente_perfil_id, saco el user_id desde pacientes
        if (! empty($turno->paciente_perfil_id)) {
            $uid = Paciente::query()
                ->where('paciente_id', (int) $turno->paciente_perfil_id)
                ->value('user_id');

            if ($uid && User::query()->whereKey((int) $uid)->exists()) {
                return (int) $uid;
            }
        }

        // 3) Último intento: si paciente_id en realidad era pacientes.paciente_id
        if (! empty($turno->paciente_id)) {
            $uid = Paciente::query()
                ->where('paciente_id', (int) $turno->paciente_id)
                ->value('user_id');

            if ($uid && User::query()->whereKey((int) $uid)->exists()) {
                return (int) $uid;
            }
        }

        throw new RuntimeException(
            "El turno #{$turno->id_turno} no tiene un paciente válido en users. Revisá turnos.paciente_id / turnos.paciente_perfil_id."
        );
    }


    /**
     * Asegura consulta en borrador y avanza paso_actual al menos hasta $pasoMinimo.
     * OJO: NO usar updateOrCreate acá para no “perder” el lockForUpdate.
     */
    private function asegurarConsultaBorrador(Turno $turno, int $userId, int $pasoMinimo): Consulta
    {
        if ((int) $turno->profesional_id !== $userId) {
            abort(403);
        }

        if (in_array((string) $turno->estado, [
            Turno::ESTADO_CANCELADO,
            Turno::ESTADO_CANCELADO_TARDE,
            Turno::ESTADO_NO_ASISTIO,
        ], true)) {
            throw new \RuntimeException('El turno está cancelado/no asistió.');
        }

        $consulta = Consulta::query()
            ->where('turno_id', $turno->id_turno)
            ->lockForUpdate()
            ->first();

        if ($consulta && (string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
            throw new \RuntimeException('La consulta ya está finalizada y no se puede modificar.');
        }

        $pasoPrevio = (int) ($consulta?->paso_actual ?? self::PASO_DATOS);
        $pasoNuevo  = max($pasoPrevio, $pasoMinimo);

        if (! $consulta) {
            $consulta = new Consulta();
            $consulta->turno_id = $turno->id_turno;
        }

        // ✅ IMPORTANTE: consultas.paciente_id = users.id (legacy)
        $consulta->paciente_id = $this->pacienteUserId ?? $this->resolverPacienteUserId($turno);
        $consulta->kinesiologa_id = $userId;
        $consulta->fecha          = $turno->fecha;
        $consulta->tipo           = Consulta::TIPO_INICIAL;
        $consulta->estado         = Consulta::ESTADO_BORRADOR;
        $consulta->paso_actual    = $pasoNuevo;

        $consulta->save();
        $consulta->refresh();

        $this->consultaIdActual = (int) $consulta->id_consulta;

        $this->pasoMaximo = (int) $consulta->paso_actual;
        $this->pasoActual = (int) ($this->pasoActual ?: $consulta->paso_actual);

        return $consulta;
    }


    private function obtenerPadecimientoTipoDefaultId(): int
    {
        if (! Schema::hasTable('padecimiento_tipo')) {
            throw new RuntimeException('No existe la tabla "padecimiento_tipo". Corré las migraciones.');
        }

        $q = DB::table('padecimiento_tipo')
            ->where('activo', 1);

        // si tu tabla tiene soft delete
        if (Schema::hasColumn('padecimiento_tipo', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        // 1) Prioridad por CÓDIGO
        $codigosPreferidos = ['GEN', 'GENERAL', 'EVAL', 'EVALUACION', 'EN_EVALUACION'];

        $id = (clone $q)
            ->whereNotNull('codigo')
            ->whereIn('codigo', $codigosPreferidos)
            ->orderByRaw("FIELD(codigo, 'GEN','GENERAL','EVAL','EVALUACION','EN_EVALUACION')")
            ->value('id_padecimiento_tipo');

        if ($id) {
            return (int) $id;
        }

        // 2) Fallback por NOMBRE
        $nombresPreferidos = ['En evaluación', 'En Evaluación', 'General', 'Generico', 'Genérico'];

        $id = (clone $q)
            ->whereIn('nombre', $nombresPreferidos)
            ->orderByRaw("FIELD(nombre, 'En evaluación','En Evaluación','General','Generico','Genérico')")
            ->value('id_padecimiento_tipo');

        if ($id) {
            return (int) $id;
        }

        // 3) Último fallback: primer tipo activo
        $id = (clone $q)
            ->orderBy('id_padecimiento_tipo')
            ->value('id_padecimiento_tipo');

        if (! $id) {
            throw new RuntimeException(
                'No hay tipos activos en "padecimiento_tipo". Creá al menos uno (ej: GEN / En evaluación).'
            );
        }

        return (int) $id;
    }


    /**
     * ✅ PASO 4 (Opción A): asegurar padecimiento ligado a la consulta.
     * Importante:
     * - Si tu tabla paciente_padecimiento es "minimal", esto funciona directo.
     * - Si tu tabla es "completa", este helper intenta completar campos si existen.
     *   Aun así, lo ideal es que tengas defaults/nullable en campos clínicos para no romper el autogenerado.
     */
    private function asegurarPadecimientoParaConsulta(Consulta $consulta): PacientePadecimiento
    {
        if (! Schema::hasTable('paciente_padecimiento')) {
            throw new RuntimeException('No existe la tabla "paciente_padecimiento". Corré las migraciones.');
        }

        // ✅ Fuente única: perfil clínico (pacientePerfilId)
        $perfilId = $this->pacientePerfilId ?? $this->resolverPacientePerfilId();

        // ✅ Ancla única: paciente + consulta (y lock dentro de TX)
        $p = PacientePadecimiento::query()
            ->where('paciente_id', (int) $perfilId)
            ->where('id_consulta', (int) $consulta->id_consulta)
            ->lockForUpdate()
            ->first();

        if ($p) {
            $this->hidratarPadecimientoUI($p);
            return $p;
        }

        // ✅ C.3: si la consulta ya está finalizada, NO creamos cosas nuevas
        if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
            throw new RuntimeException('La consulta está finalizada. No se puede crear un padecimiento nuevo.');
        }

        // ✅ Defaults consistentes
        $nombre = 'Padecimiento en evaluación';
        $dxId = (int) ($this->diagnosticoFuncionalId ?? 0);

        if ($dxId > 0 && isset($this->diagnosticosOptions[$dxId])) {
            $nombre = (string) $this->diagnosticosOptions[$dxId];
        }

        $tipoId = (int) $this->obtenerPadecimientoTipoDefaultId();
        if ($tipoId <= 0) {
            throw new RuntimeException('No hay un tipo de padecimiento por defecto configurado.');
        }

        $p = PacientePadecimiento::create([
            'paciente_id'          => (int) $perfilId,
            'id_consulta'          => (int) $consulta->id_consulta,
            'id_padecimiento_tipo' => $tipoId,
            'id_zona_anatomica'    => null,

            'nombre'               => $nombre,
            'fecha_inicio'         => $consulta->fecha,

            'lateralidad'          => 'no_especificada',
            'severidad'            => 'no_especificada',
            'estado'               => 'en_progreso',
            'origen'               => 'consulta',

            'notas'                => null,
        ]);

        $this->hidratarPadecimientoUI($p);

        return $p;
    }


    private function hidratarPadecimientoUI(PacientePadecimiento $p): void
    {
        $this->padecimientoIdActual = (int) $p->id_padecimiento;
        $this->padecimientoTipoIdActual = (int) $p->id_padecimiento_tipo;
        $this->padecimientoNombreActual = (string) $p->nombre;

        $tipoQ = DB::table('padecimiento_tipo')
            ->where('id_padecimiento_tipo', (int) $p->id_padecimiento_tipo);

        if (Schema::hasColumn('padecimiento_tipo', 'deleted_at')) {
            $tipoQ->whereNull('deleted_at');
        }

        $this->padecimientoTipoNombreActual = (string) ($tipoQ->value('nombre') ?? '');
    }


    // ✅ Clamp EVA 0..10 en vivo
    public function updatedEvaDolor($value): void
    {
        if ($this->soloLectura) {
            return;
        }

        if ($value === '' || $value === null) {
            $this->evaDolor = null;
            return;
        }

        $n = (int) $value;
        $this->evaDolor = max(0, min(10, $n));
    }

    public function addRomRow(): void
    {
        if (! $this->puedeEditarEvaluacionRom()) {
            $this->avisoWizard = 'Los ROM solo se pueden cargar/editar cuando el turno ya finalizó.';
            return;
        }

        $this->roms[] = [
            'id_movimiento' => null,
            'id_metodo'     => null,
            'lado'          => 'der',
            'valor_grados'  => null,
            'observaciones' => null,
        ];

        $this->romErrors[] = null;

        $this->recalcularErroresRom();
        $this->cargarHistorialRom();
    }

    public function removeRomRow(int $index): void
    {
        if (! $this->puedeEditarEvaluacionRom()) {
            $this->avisoWizard = 'Los ROM solo se pueden editar cuando el turno ya finalizó.';
            return;
        }

        if (! isset($this->roms[$index])) {
            return;
        }

        unset($this->roms[$index], $this->romErrors[$index]);

        $this->roms = array_values($this->roms);
        $this->romErrors = array_values($this->romErrors);

        if (empty($this->roms)) {
            $this->roms = [[
                'id_movimiento' => null,
                'id_metodo'     => null,
                'lado'          => 'der',
                'valor_grados'  => null,
                'observaciones' => null,
            ]];
            $this->romErrors = [null];
        }

        $this->recalcularErroresRom();
        $this->cargarHistorialRom();
    }

    // =========================================================
    // Helpers rango normal (cache)
    // =========================================================
    public function rangoNormalTexto(?int $movimientoId): ?string
    {
        if (! $movimientoId) {
            return null;
        }

        $meta = $this->movimientosMeta[$movimientoId] ?? null;
        if (! $meta) {
            return null;
        }

        $min = $meta['min'] ?? null;
        $max = $meta['max'] ?? null;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null) {
            return "Normal: {$min}–{$max}°";
        }

        if ($min !== null) {
            return "Normal: ≥ {$min}°";
        }

        return "Normal: ≤ {$max}°";
    }

    public function romFueraDeRango(?int $movimientoId, $valorGrados): bool
    {
        if (! $movimientoId) {
            return false;
        }

        if ($valorGrados === null || $valorGrados === '') {
            return false;
        }

        $valor = is_numeric($valorGrados) ? (float) $valorGrados : null;
        if ($valor === null) {
            return false;
        }

        $meta = $this->movimientosMeta[$movimientoId] ?? null;
        if (! $meta) {
            return false;
        }

        $min = $meta['min'] ?? null;
        $max = $meta['max'] ?? null;

        if ($min === null && $max === null) {
            return false;
        }

        if ($min !== null && $valor < $min) {
            return true;
        }

        if ($max !== null && $valor > $max) {
            return true;
        }

        return false;
    }

    // =========================================================
    // Normalización + validaciones ROM
    // =========================================================
    private function normalizarRomsParaGuardar(): array
    {
        $limpios = [];

        foreach (($this->roms ?? []) as $i => $r) {
            $mov  = $r['id_movimiento'] ?? null;
            $met  = $r['id_metodo'] ?? null;
            $lado = $r['lado'] ?? null;

            if (empty($mov) || empty($met) || empty($lado)) {
                continue;
            }

            $limpios[] = [
                'id_movimiento' => (int) $mov,
                'id_metodo'     => (int) $met,
                'lado'          => (string) $lado,
                'valor_grados'  => isset($r['valor_grados']) && $r['valor_grados'] !== '' ? (int) $r['valor_grados'] : null,
                'observaciones' => isset($r['observaciones']) && trim((string) $r['observaciones']) !== ''
                    ? trim((string) $r['observaciones'])
                    : null,
                '_orig_index'   => (int) $i,
            ];
        }

        return $limpios;
    }

    private function validarDuplicadosMovLado(array $romsLimpios, bool $marcarUI = false): void
    {
        $seen = [];

        if ($marcarUI) {
            $this->romErrors = array_fill(0, count($this->roms ?? []), null);
        }

        foreach ($romsLimpios as $idx => $row) {
            $mov  = $row['id_movimiento'] ?? null;
            $lado = $row['lado'] ?? null;

            if (empty($mov) || empty($lado)) {
                continue;
            }

            $key = (int) $mov . '|' . (string) $lado;

            if (isset($seen[$key])) {
                $idxA = $seen[$key];
                $idxB = $idx;

                $filaA = ($romsLimpios[$idxA]['_orig_index'] ?? $idxA) + 1;
                $filaB = ($romsLimpios[$idxB]['_orig_index'] ?? $idxB) + 1;

                if ($marcarUI) {
                    $msg = 'Duplicado (mismo movimiento + lado).';
                    $origA = $romsLimpios[$idxA]['_orig_index'] ?? null;
                    $origB = $romsLimpios[$idxB]['_orig_index'] ?? null;

                    if ($origA !== null) $this->romErrors[$origA] = $msg;
                    if ($origB !== null) $this->romErrors[$origB] = $msg;
                }

                throw new \RuntimeException("ROM duplicado: mismo movimiento + lado en filas {$filaA} y {$filaB}.");
            }

            $seen[$key] = $idx;
        }
    }

    private function validarBilateralVsUnilateral(array $romsLimpios, bool $marcarUI = false): void
    {
        $byMov = [];

        foreach ($romsLimpios as $idx => $row) {
            $mov = $row['id_movimiento'] ?? null;
            $lado = $row['lado'] ?? null;

            if (! $mov || ! $lado) continue;

            $mov = (int) $mov;
            $lado = (string) $lado;

            $byMov[$mov] ??= ['bilateral' => [], 'der' => [], 'izq' => []];

            if ($lado === 'bilateral') $byMov[$mov]['bilateral'][] = $idx;
            elseif ($lado === 'der')  $byMov[$mov]['der'][] = $idx;
            elseif ($lado === 'izq')  $byMov[$mov]['izq'][] = $idx;
        }

        foreach ($byMov as $movId => $flags) {
            $hayBil = count($flags['bilateral']) > 0;
            $hayUni = count($flags['der']) > 0 || count($flags['izq']) > 0;

            if ($hayBil && $hayUni) {
                if ($marcarUI) {
                    $msg = 'Inconsistente: si es bilateral no cargues der/izq (y viceversa).';

                    foreach (array_merge($flags['bilateral'], $flags['der'], $flags['izq']) as $idx) {
                        $orig = $romsLimpios[$idx]['_orig_index'] ?? null;
                        if ($orig !== null) $this->romErrors[$orig] = $msg;
                    }
                }

                $nombreMov = $this->movimientosOptions[$movId] ?? ("Movimiento #{$movId}");
                throw new \RuntimeException("ROM inconsistente en '{$nombreMov}': no mezcles 'bilateral' con 'der/izq'.");
            }
        }
    }

    private function recalcularErroresRom(): void
    {
        try {
            $this->romErrors = array_fill(0, count($this->roms ?? []), null);
            $romsLimpios = $this->normalizarRomsParaGuardar();
            $this->validarDuplicadosMovLado($romsLimpios, true);
            $this->validarBilateralVsUnilateral($romsLimpios, true);
        } catch (\Throwable $e) {
            // romErrors ya quedó marcado
        }
    }

    public function updatedRoms(): void
    {
        if (! $this->puedeEditarEvaluacionRom()) {
            return;
        }

        $this->recalcularErroresRom();
        $this->cargarHistorialRom();
    }

    // =========================================================
    // Histórico ROM (cache)
    // =========================================================
    private function cargarHistorialRom(): void
    {
        $this->romHistory = [];

        $romsLimpios = $this->normalizarRomsParaGuardar();
        if (empty($romsLimpios) || ! $this->pacienteUserId) return;

        $movIds = collect($romsLimpios)->pluck('id_movimiento')->unique()->values()->all();
        $lados  = collect($romsLimpios)->pluck('lado')->unique()->values()->all();
        if (empty($movIds) || empty($lados)) return;

        $q = EvaluacionRom::query()
            ->select([
                'evaluacion_rom.id_movimiento',
                'evaluacion_rom.lado',
                'evaluacion_rom.valor_grados',
                'evaluacion_rom.id_metodo',
                'c.fecha as consulta_fecha',
                'c.id_consulta as consulta_id',
            ])
            ->join('evaluacion_funcional as ef', 'ef.id_eval_func', '=', 'evaluacion_rom.id_eval_func')
            ->join('consultas as c', 'c.id_consulta', '=', 'ef.id_consulta')
            ->where('c.paciente_id', $this->pacienteUserId)
            ->whereIn('evaluacion_rom.id_movimiento', $movIds)
            ->whereIn('evaluacion_rom.lado', $lados)
            ->whereNotNull('evaluacion_rom.valor_grados')
            ->orderByDesc('c.fecha')
            ->orderByDesc('evaluacion_rom.id_eval_rom')
            ->limit(300);

        if ($this->consultaIdActual) {
            $q->where('c.id_consulta', '!=', $this->consultaIdActual);
        }

        $rows = $q->get();

        foreach ($rows as $r) {
            $key = ((int) $r->id_movimiento) . '|' . ((string) $r->lado);

            $this->romHistory[$key] ??= [];
            if (count($this->romHistory[$key]) >= 5) continue;

            $this->romHistory[$key][] = [
                'fecha'  => (string) $r->consulta_fecha,
                'valor'  => $r->valor_grados !== null ? (int) $r->valor_grados : null,
                'metodo' => $this->metodosOptions[(int) $r->id_metodo] ?? null,
            ];
        }
    }

    public function historialRom(?int $movId, ?string $lado): array
    {
        if (! $movId || ! $lado) return [];

        $key = ((int) $movId) . '|' . ((string) $lado);
        return $this->romHistory[$key] ?? [];
    }

    // =========================================================
    // Guardar PASO 1 (Datos) — desbloquea Anamnesis
    // =========================================================
    public function guardarDatosYContinuar(): void
    {
        $this->avisoWizard = null;

        $userId = (int) Auth::id();
        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        // UI guard: si está en solo lectura, no intentamos
        if (! $this->puedeEditarAnamnesis()) {
            $this->soloLectura = true;

            Notification::make()
                ->title('No se puede continuar')
                ->body('La consulta está en solo lectura (finalizada o turno cancelado/no asistió).')
                ->warning()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($userId) {
                $turno = Turno::query()
                    ->where('id_turno', $this->turno->id_turno)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $turno->profesional_id !== $userId) {
                    abort(403);
                }

                // Hardening servidor: estado real del turno en BD
                if (in_array((string) $turno->estado, [
                    Turno::ESTADO_CANCELADO,
                    Turno::ESTADO_CANCELADO_TARDE,
                    Turno::ESTADO_NO_ASISTIO,
                ], true)) {
                    throw new RuntimeException('No se puede continuar: el turno está cancelado/no asistió.');
                }

                // Crea la consulta si no existe y sube paso_actual al menos a ANAMNESIS
                $consulta = $this->asegurarConsultaBorrador($turno, $userId, self::PASO_ANAMNESIS);

                // Bloqueo total post-cierre (BD manda)
                if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                    throw new RuntimeException('La consulta está finalizada. No se puede modificar.');
                }

                $this->consultaIdActual = (int) $consulta->id_consulta;

                // DB = fuente única
                $this->pasoMaximo = (int) ($consulta->paso_actual ?? self::PASO_DATOS);

                // UI avanza
                $this->pasoActual = self::PASO_ANAMNESIS;
            });

            $this->soloLectura = ! $this->puedeEditarAnamnesis();

            Notification::make()
                ->title('Paso desbloqueado')
                ->body('Podés continuar con Anamnesis.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            $this->avisoWizard = $e->getMessage();

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =========================================================
    // Guardar PASO 2 (Anamnesis)
    // =========================================================
    public function guardarAnamnesis(): void
    {
        $userId = (int) Auth::id();

        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            Notification::make()
                ->title('Acceso denegado')
                ->body('No tenés permiso para guardar esta anamnesis.')
                ->danger()
                ->send();
            return;
        }

        // UI guard
        if (! $this->puedeEditarAnamnesis()) {
            $this->soloLectura = true;

            Notification::make()
                ->title('No se puede guardar')
                ->body('La anamnesis no puede modificarse porque el turno está cancelado/no asistió o la consulta ya fue finalizada.')
                ->warning()
                ->send();
            return;
        }

        $motivo  = trim((string) $this->motivoConsulta);
        $resumen = trim((string) $this->resumen);

        if (mb_strlen($motivo) > 2000) {
            Notification::make()->title('Motivo demasiado largo')->body('El motivo no puede superar 2000 caracteres.')->danger()->send();
            return;
        }

        if (mb_strlen($resumen) > 5000) {
            Notification::make()->title('Texto demasiado largo')->body('El resumen no puede superar 5000 caracteres.')->danger()->send();
            return;
        }

        if ($this->evaDolor !== null && ((int) $this->evaDolor < 0 || (int) $this->evaDolor > 10)) {
            Notification::make()->title('EVA inválida')->body('El dolor (EVA) debe estar entre 0 y 10.')->danger()->send();
            return;
        }

        if ($this->limitacionFuncional !== null && ! array_key_exists($this->limitacionFuncional, $this->limitacionOptions)) {
            Notification::make()->title('Limitación inválida')->danger()->send();
            return;
        }

        try {
            DB::transaction(function () use ($userId, $motivo, $resumen) {
                $turno = Turno::query()
                    ->where('id_turno', $this->turno->id_turno)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $turno->profesional_id !== $userId) {
                    abort(403);
                }

                // Hardening servidor: estado real del turno en BD
                if (in_array((string) $turno->estado, [
                    Turno::ESTADO_CANCELADO,
                    Turno::ESTADO_CANCELADO_TARDE,
                    Turno::ESTADO_NO_ASISTIO,
                ], true)) {
                    throw new RuntimeException('No se puede guardar: el turno está cancelado/no asistió.');
                }

                // Subimos a PASO_ANAMNESIS (no más)
                $consulta = $this->asegurarConsultaBorrador($turno, $userId, self::PASO_ANAMNESIS);

                // Bloqueo total post-cierre
                if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                    throw new RuntimeException('La consulta está finalizada. No se puede modificar.');
                }

                $consulta->update([
                    'resumen'     => $resumen !== '' ? $resumen : null,
                    'paso_actual' => max((int) ($consulta->paso_actual ?? self::PASO_DATOS), self::PASO_ANAMNESIS),
                ]);

                EvaluacionFuncional::updateOrCreate(
                    ['id_consulta' => $consulta->id_consulta],
                    [
                        'fecha'                => $consulta->fecha,
                        'eva_dolor'            => $this->evaDolor !== null ? (int) $this->evaDolor : null,
                        'motivo_consulta'      => $motivo !== '' ? $motivo : null,
                        'limitacion_funcional' => $this->limitacionFuncional ?: null,
                        'texto'                => null,
                    ]
                );

                // Wizard desde BD
                $this->consultaIdActual = (int) $consulta->id_consulta;
                $this->pasoMaximo = (int) $consulta->paso_actual;
                $this->pasoActual = self::PASO_ANAMNESIS;
            });

            $this->turno->refresh();
            $this->soloLectura = ! $this->puedeEditarAnamnesis();

            Notification::make()
                ->title('Anamnesis guardada')
                ->body('Se guardó como borrador. No se modificaron los ROM ni el estado del turno.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Error al guardar anamnesis')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function guardarAnamnesisYContinuar(): void
    {
        $this->guardarAnamnesis();

        // Si quedó en solo lectura, no tiene sentido avanzar
        if ($this->soloLectura) {
            return;
        }

        // Si tu lógica de wizard usa pasoMaximo, refrescá/asegurá que esté actualizado.
        // Idealmente, en asegurarConsultaBorrador ya te sube paso_actual y vos lo reflejás en $this->pasoMaximo.

        if ((int) $this->pasoMaximo >= self::PASO_EVALUACION) {
            $this->pasoActual = self::PASO_EVALUACION;
        }
    }

    // =========================================================
    // Guardar PASO 3 (ROM)
    // =========================================================
    public function guardar(): void
    {
        $userId = (int) Auth::id();

        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            Notification::make()->title('Acceso denegado')->body('No tenés permiso para guardar esta evaluación.')->danger()->send();
            return;
        }

        if (! $this->puedeEditarEvaluacionRom()) {
            Notification::make()
                ->title('No se puede guardar todavía')
                ->body('La evaluación (ROM) solo puede guardarse cuando el turno ya finalizó y no está cancelado/no asistió.')
                ->warning()
                ->send();
            return;
        }

        $resumen = trim((string) $this->resumen);
        if (mb_strlen($resumen) > 5000) {
            Notification::make()->title('Texto demasiado largo')->body('El resumen no puede superar 5000 caracteres.')->danger()->send();
            return;
        }

        $motivo = trim((string) $this->motivoConsulta);
        if (mb_strlen($motivo) > 2000) {
            Notification::make()->title('Motivo demasiado largo')->body('El motivo no puede superar 2000 caracteres.')->danger()->send();
            return;
        }

        if ($this->evaDolor !== null && ((int) $this->evaDolor < 0 || (int) $this->evaDolor > 10)) {
            Notification::make()->title('EVA inválida')->body('El dolor (EVA) debe estar entre 0 y 10.')->danger()->send();
            return;
        }

        if ($this->limitacionFuncional !== null && ! array_key_exists($this->limitacionFuncional, $this->limitacionOptions)) {
            Notification::make()->title('Limitación inválida')->danger()->send();
            return;
        }

        $romsLimpios = $this->normalizarRomsParaGuardar();

        try {
            $this->romErrors = array_fill(0, count($this->roms ?? []), null);
            $this->validarDuplicadosMovLado($romsLimpios, true);
            $this->validarBilateralVsUnilateral($romsLimpios, true);
        } catch (\Throwable $e) {
            Notification::make()->title('Revisá los ROM')->body($e->getMessage())->danger()->send();
            return;
        }

        try {
            DB::transaction(function () use ($userId, $resumen, $romsLimpios, $motivo) {
                $turno = Turno::query()
                    ->where('id_turno', $this->turno->id_turno)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $turno->profesional_id !== $userId) {
                    abort(403);
                }

                // Hardening servidor: estado real del turno en BD
                if (in_array((string) $turno->estado, [
                    Turno::ESTADO_CANCELADO,
                    Turno::ESTADO_CANCELADO_TARDE,
                    Turno::ESTADO_NO_ASISTIO,
                ], true)) {
                    throw new RuntimeException('No se puede guardar: el turno está cancelado/no asistió.');
                }

                // Guardamos ROM, por lo tanto el paso mínimo alcanzado es EVALUACION
                $consulta = $this->asegurarConsultaBorrador($turno, $userId, self::PASO_EVALUACION);

                // Bloqueo total post-cierre
                if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                    throw new RuntimeException('La consulta está finalizada. No se puede modificar.');
                }

                $consulta->update([
                    'resumen'     => $resumen !== '' ? $resumen : null,
                    'paso_actual' => max((int) ($consulta->paso_actual ?? self::PASO_DATOS), self::PASO_EVALUACION),
                ]);

                $evalFunc = EvaluacionFuncional::updateOrCreate(
                    ['id_consulta' => $consulta->id_consulta],
                    [
                        'fecha'                => $consulta->fecha,
                        'eva_dolor'            => $this->evaDolor !== null ? (int) $this->evaDolor : null,
                        'motivo_consulta'      => $motivo !== '' ? $motivo : null,
                        'limitacion_funcional' => $this->limitacionFuncional ?: null,
                        'texto'                => null,
                    ]
                );

                // Re-validación (ahora “definitiva”)
                $this->validarDuplicadosMovLado($romsLimpios, false);
                $this->validarBilateralVsUnilateral($romsLimpios, false);

                EvaluacionRom::query()
                    ->where('id_eval_func', $evalFunc->id_eval_func)
                    ->delete();

                foreach ($romsLimpios as $row) {
                    EvaluacionRom::create([
                        'id_eval_func'  => $evalFunc->id_eval_func,
                        'id_movimiento' => $row['id_movimiento'],
                        'id_metodo'     => $row['id_metodo'],
                        'lado'          => $row['lado'],
                        'valor_grados'  => $row['valor_grados'],
                        'observaciones' => $row['observaciones'],
                    ]);
                }

                // Wizard desde BD
                $this->consultaIdActual = (int) $consulta->id_consulta;
                $this->pasoMaximo = (int) $consulta->paso_actual;
                $this->pasoActual = self::PASO_EVALUACION;
            });

            $this->turno->refresh();
            $this->cargarHistorialRom();

            $this->soloLectura = ! $this->puedeEditarAnamnesis();

            Notification::make()
                ->title('Evaluación guardada (borrador)')
                ->body('Se guardó como borrador. El turno se marcará como atendido al finalizar la consulta.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Error al guardar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function guardarEvaluacionYContinuar(): void
    {
        $this->guardar();

        if ($this->soloLectura) {
            return;
        }

        if ((int) $this->pasoMaximo >= self::PASO_DIAGNOSTICO) {
            $this->pasoActual = self::PASO_DIAGNOSTICO;
        }
    }

    // =========================================================
    // Guardar PASO 4 (Diagnóstico) — HARDENED + ANCLA CLÍNICA
    // =========================================================
    public function guardarDiagnostico(): void
    {
        $userId = (int) Auth::id();

        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        // Diagnóstico lo tratamos como “clínico” (tipo anamnesis): no exige turno finalizado
        if (! $this->puedeEditarAnamnesis()) {
            $this->soloLectura = true;

            Notification::make()
                ->title('No se puede guardar')
                ->body('El turno está cancelado/no asistió o la consulta ya fue finalizada.')
                ->warning()
                ->send();
            return;
        }

        if (! $this->diagnosticoFuncionalId || ! isset($this->diagnosticosOptions[$this->diagnosticoFuncionalId])) {
            Notification::make()
                ->title('Falta diagnóstico')
                ->body('Seleccioná un diagnóstico funcional.')
                ->danger()
                ->send();
            return;
        }

        $notas = trim((string) $this->diagnosticoNotas);
        if (mb_strlen($notas) > 5000) {
            Notification::make()
                ->title('Notas demasiado largas')
                ->body('Las notas no pueden superar 5000 caracteres.')
                ->danger()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($userId, $notas) {
                $turno = Turno::query()
                    ->where('id_turno', $this->turno->id_turno)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Seguridad dentro de TX (por si cambió algo entre requests)
                if ((int) $turno->profesional_id !== $userId) {
                    abort(403);
                }

                // Hardening servidor: si el turno quedó cancelado/no asistió en DB
                if (in_array((string) $turno->estado, [
                    Turno::ESTADO_CANCELADO,
                    Turno::ESTADO_CANCELADO_TARDE,
                    Turno::ESTADO_NO_ASISTIO,
                ], true)) {
                    throw new RuntimeException('No se puede guardar: el turno está cancelado/no asistió.');
                }

                $consulta = $this->asegurarConsultaBorrador($turno, $userId, self::PASO_DIAGNOSTICO);

                // ✅ C.3: Bloqueo total post-cierre (BD manda)
                if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                    throw new RuntimeException('La consulta está finalizada. No se puede modificar el diagnóstico.');
                }

                $padecimiento = $this->asegurarPadecimientoParaConsulta($consulta);

                // “Principal” único: apagamos otros principales
                PadecimientoDiagnostico::query()
                    ->where('id_padecimiento', $padecimiento->id_padecimiento)
                    ->where('es_principal', true)
                    ->update(['es_principal' => false]);

                // Guardamos/actualizamos principal vigente desde fecha de consulta
                // (si tu "vigente_desde" es fecha-hora, podés usar $consulta->fecha ?? now())
                PadecimientoDiagnostico::updateOrCreate(
                    [
                        'id_padecimiento'          => (int) $padecimiento->id_padecimiento,
                        'id_diagnostico_funcional' => (int) $this->diagnosticoFuncionalId,
                        'vigente_desde'            => $consulta->fecha,
                    ],
                    [
                        'es_principal'  => true,
                        'vigente_hasta' => null,
                        'notas'         => $notas !== '' ? $notas : null,
                    ]
                );

                // ✅ C.2: Diagnóstico = ancla clínica -> el paso queda “cerrado” en BD
                $nuevoMax = max((int) ($consulta->paso_actual ?? self::PASO_DATOS), self::PASO_DIAGNOSTICO);
                if ($nuevoMax !== (int) ($consulta->paso_actual ?? self::PASO_DATOS)) {
                    $consulta->update(['paso_actual' => $nuevoMax]);
                }

                // Refrescar wizard desde BD (verdad única)
                $this->pasoMaximo = $nuevoMax;
                $this->pasoActual = self::PASO_DIAGNOSTICO;

                // UI (id, nombre, tipo...)
                $this->hidratarPadecimientoUI($padecimiento);
            });

            // Recalcular soloLectura según reglas actuales (post-TX)
            $this->soloLectura = ! $this->puedeEditarAnamnesis();

            Notification::make()
                ->title('Diagnóstico guardado')
                ->body('Se guardó como borrador y quedó anclado al padecimiento.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Error al guardar diagnóstico')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =========================================================
    // Wizard lógico — MODO PRO
    // =========================================================
    public function irAlPaso(int $paso): void
    {
        $paso = max(self::PASO_DATOS, min(self::PASO_CIERRE, $paso));
        $this->avisoWizard = null;

        $userId = (int) Auth::id();

        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            $this->avisoWizard = 'No tenés permiso para navegar esta consulta.';
            return;
        }

        if (! $this->puedeEditarAnamnesis()) {
            $this->soloLectura = true;

            $consulta = $this->consultaIdActual ? Consulta::find($this->consultaIdActual) : null;

            if ($consulta && (string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                $this->pasoMaximo = self::PASO_CIERRE;
            }
            // si NO es finalizada, NO toques pasoMaximo

            $this->avisoWizard = 'La consulta está en solo lectura...';
            $this->pasoActual = $paso;
            return;
        }

        $this->soloLectura = false;

        try {
            DB::transaction(function () use ($userId, $paso) {
                $turno = Turno::query()
                    ->where('id_turno', $this->turno->id_turno)
                    ->lockForUpdate()
                    ->firstOrFail();

                $consulta = $this->asegurarConsultaBorrador($turno, $userId, self::PASO_DATOS);

                $max = (int) ($consulta->paso_actual ?? self::PASO_DATOS);

                if ($paso > $max) {
                    $this->avisoWizard = 'Primero guardá el paso actual para desbloquear el siguiente.';
                    return;
                }

                $this->pasoActual = $paso;
                $this->pasoMaximo = $max;
            });
        } catch (\Throwable $e) {
            report($e);
            $this->avisoWizard = $e->getMessage();

            Notification::make()
                ->title('No se pudo cambiar el paso')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // =========================================================
    // A.1 — Validaciones mínimas antes de finalizar (BD como verdad)
    // =========================================================
    private function validarMinimosParaFinalizar(Consulta $consulta): void
    {
        // 1) Anamnesis
        $ef = EvaluacionFuncional::where('id_consulta', $consulta->id_consulta)->first();
        $motivoOk = $ef && trim((string) $ef->motivo_consulta) !== '';
        $evaOk    = $ef && $ef->eva_dolor !== null;

        if (! $motivoOk && ! $evaOk) {
            throw new RuntimeException('Falta anamnesis mínima: completá Motivo o EVA.');
        }

        // 2) ROM
        if (! $ef) {
            throw new RuntimeException('Falta Evaluación Funcional (no existe cabecera de evaluación).');
        }

        $hayRom = EvaluacionRom::where('id_eval_func', $ef->id_eval_func)
            ->whereNotNull('valor_grados')
            ->exists();

        if (! $hayRom) {
            throw new RuntimeException('Falta evaluación ROM: cargá al menos un ROM con grados.');
        }

        // 3) Diagnóstico principal (anclado a padecimiento de esta consulta)
        $perfilId = $this->pacientePerfilId ?? $this->resolverPacientePerfilId();

        $p = PacientePadecimiento::where('paciente_id', $perfilId)
            ->where('id_consulta', $consulta->id_consulta)
            ->first();

        if (! $p) {
            throw new RuntimeException('Falta diagnóstico: primero guardá el paso Diagnóstico.');
        }

        $principal = PadecimientoDiagnostico::where('id_padecimiento', $p->id_padecimiento)
            ->where('es_principal', true)
            ->orderByDesc('vigente_desde') // si existe la columna, si no, sacalo
            ->first();

        if (! $principal || ! $principal->id_diagnostico_funcional) {
            throw new RuntimeException('Falta diagnóstico principal: seleccioná uno y guardá.');
        }
    }

    // =========================================================
    // Finalizar consulta (Wizard)
    // =========================================================
    public function finalizarConsulta(): void
    {
        $userId = (int) Auth::id();

        if (! $userId || (int) $this->turno->profesional_id !== $userId) {
            Notification::make()
                ->title('Acceso denegado')
                ->body('No tenés permiso para finalizar esta consulta.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->puedeEditarEvaluacionRom()) {
            Notification::make()
                ->title('No se puede finalizar todavía')
                ->body('Solo podés finalizar cuando el turno ya terminó y no está cancelado/no asistió.')
                ->warning()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($userId) {
                $turno = Turno::query()
                    ->where('id_turno', $this->turno->id_turno)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $turno->profesional_id !== $userId) {
                    abort(403);
                }

                // ✅ Hardening servidor: si el turno quedó cancelado/no asistió en DB
                if (in_array((string) $turno->estado, [
                    Turno::ESTADO_CANCELADO,
                    Turno::ESTADO_CANCELADO_TARDE,
                    Turno::ESTADO_NO_ASISTIO,
                ], true)) {
                    throw new RuntimeException('No se puede finalizar: el turno está cancelado/no asistió.');
                }

                $consulta = $this->asegurarConsultaBorrador($turno, $userId, self::PASO_CIERRE);

                // ✅ Idempotencia: si ya está finalizada, dejamos el estado estable y salimos
                if ((string) $consulta->estado === Consulta::ESTADO_FINALIZADA) {
                    $this->consultaIdActual = (int) $consulta->id_consulta;
                    $this->pasoActual = self::PASO_CIERRE;
                    $this->pasoMaximo = self::PASO_CIERRE;
                    $this->soloLectura = true;

                    $this->turno->refresh();
                    return;
                }

                // ✅ A.1: validar mínimos en BD antes de finalizar
                $this->validarMinimosParaFinalizar($consulta);

                // ✅ recién acá finalizás
                $consulta->update([
                    'estado'      => Consulta::ESTADO_FINALIZADA,
                    'paso_actual' => self::PASO_CIERRE,
                ]);

                if ((string) $turno->estado !== Turno::ESTADO_ATENDIDO) {
                    $turno->update(['estado' => Turno::ESTADO_ATENDIDO]);
                }

                $this->consultaIdActual = (int) $consulta->id_consulta;
                $this->pasoActual = self::PASO_CIERRE;
                $this->pasoMaximo = self::PASO_CIERRE;
                $this->soloLectura = true;

                $this->turno->refresh();
            });

            Notification::make()
                ->title('Consulta finalizada')
                ->body('La consulta se marcó como finalizada y el turno quedó como atendido.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Error al finalizar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
