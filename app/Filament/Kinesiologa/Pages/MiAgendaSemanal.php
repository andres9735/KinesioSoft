<?php

namespace App\Filament\Kinesiologa\Pages;

use App\Models\BloqueDisponibilidad;
use App\Models\Consultorio;
use App\Models\ExcepcionDisponibilidad;
use App\Models\Turno;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MiAgendaSemanal extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $title           = 'Mis horarios atención';
    protected static ?string $navigationGroup = 'Turnos y Consultas';
    protected static ?int    $navigationSort  = 50;

    /** Vista Livewire/Blade asociada */
    protected static string $view = 'filament.kinesiologa.pages.mi-agenda-semanal';

    /** ID de profesional afectado (kinesióloga logueada; admin puede elegir via query ?user_id=) */
    public int $profesionalId;

    /** Select de consultorios: [id => nombre] */
    public array $consultorios = [];

    /** Duración global (min) para los bloques a crear/actualizar */
    public int $duracion = 45;

    /**
     * Estado por día (1..6 = Lunes..Sábado; 0=Domingo si se habilita):
     * [
     *   1 => [
     *     'consultorio_id' => int|null,
     *     'maniana' => ['enabled'=>bool,'desde'=>'08:00','hasta'=>'12:00'],
     *     'tarde'   => ['enabled'=>bool,'desde'=>'16:00','hasta'=>'20:00'],
     *   ],
     *   ...
     * ]
     */
    public array $estado = [];

    /** Horarios por defecto para los tramos de la UI */
    public array $default = [
        'maniana' => ['desde' => '08:00', 'hasta' => '12:00'],
        'tarde'   => ['desde' => '16:00', 'hasta' => '20:00'],
    ];

    /** Días visibles (Lunes..Sábado). Podés habilitar Domingo agregando 0 => 'Domingo' */
    public array $dias = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        // 0 => 'Domingo',
    ];

    /** ========= Excepciones en esta página ========= */

    /** Listado de excepciones del profesional (últimas primero). */
    public array $excepciones = [];

    /**
     * Estado del formulario de "nueva excepción".
     * - bloqueado=true => se ignoran hora_desde/hora_hasta
     */
    public array $nuevaExcepcion = [
        'fecha'       => null,
        'bloqueado'   => true,
        'hora_desde'  => null,
        'hora_hasta'  => null,
        'motivo'      => null,
    ];

    /** Reset inteligente: mantener fecha al crear excepción */
    public bool $mantenerFechaExcepcion = true;

    /** Rango de carga (por si querés filtrar). Por defecto cargo todas. */
    public ?string $filtroExcepcionesDesde = null; // e.g. '2025-01-01'
    public ?string $filtroExcepcionesHasta = null; // e.g. '2025-12-31'

    /** ===================================================== */

    /** Quién ve esta página en el menú */
    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $u */
        $u = Filament::auth()->user();
        return $u?->hasAnyRole(['Kinesiologa', 'Administrador']) ?? false;
    }

    public function mount(): void
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        // Por defecto, la propia kinesiologa:
        $this->profesionalId = $user->id;

        // Si es admin, puede editar a otra profesional con ?user_id=ID (sólo si existe y tiene rol correcto)
        if ($user->hasRole('Administrador')) {
            $requestUserId = (int) request()->query('user_id', 0);
            if ($requestUserId > 0) {
                $target = User::query()->find($requestUserId);
                if ($target && $target->hasRole('Kinesiologa')) {
                    $this->profesionalId = (int) $target->id;
                }
            }
        }

        // Cargar consultorios para el select (usar PK real: id_consultorio)
        $this->consultorios = Consultorio::query()
            ->orderBy('nombre')
            ->pluck('nombre', 'id_consultorio')
            ->toArray();

        $this->cargarDesdeBD();
        $this->cargarExcepciones();
    }

    /** Carga bloques actuales y arma el estado de la UI */
    protected function cargarDesdeBD(): void
    {
        // Estado base (todo apagado con defaults)
        $baseDia = [
            'consultorio_id' => null,
            'maniana' => [
                'enabled' => true,
                'desde'   => $this->default['maniana']['desde'],
                'hasta'   => $this->default['maniana']['hasta'],
            ],
            'tarde'   => [
                'enabled' => true,
                'desde'   => $this->default['tarde']['desde'],
                'hasta'   => $this->default['tarde']['hasta'],
            ],
        ];

        $this->estado = [];
        foreach ($this->dias as $nro => $_) {
            $this->estado[$nro] = $baseDia;
        }

        // Traer bloques existentes del profesional (activos) y mapearlos
        $bloques = BloqueDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId)
            ->whereIn('dia_semana', array_keys($this->dias))
            ->where('activo', true)
            ->orderBy('dia_semana')
            ->orderBy('hora_desde')
            ->get();

        // Si encontramos algún bloque, tomamos su duración como sugerencia global
        if ($bloques->isNotEmpty()) {
            $this->duracion = (int) ($bloques->first()->duracion_minutos ?: 45);
        }

        foreach ($bloques as $b) {
            $dia   = (int) $b->dia_semana;
            $desde = Carbon::createFromFormat('H:i:s', $b->hora_desde)->format('H:i');
            $hasta = Carbon::createFromFormat('H:i:s', $b->hora_hasta)->format('H:i');

            // Fijar consultorio del día si no estaba seteado aún (tomo el primero que aparezca)
            if ($this->estado[$dia]['consultorio_id'] === null) {
                $this->estado[$dia]['consultorio_id'] = $b->consultorio_id;
            }

            // Regla: si termina antes o igual a las 13:00 lo tratamos como "mañana", si no, "tarde"
            if ($hasta <= '13:00') {
                $this->estado[$dia]['maniana'] = [
                    'enabled' => true,
                    'desde'   => $desde,
                    'hasta'   => $hasta,
                ];
            } else {
                $this->estado[$dia]['tarde'] = [
                    'enabled' => true,
                    'desde'   => $desde,
                    'hasta'   => $hasta,
                ];
            }
        }
    }

    /** ========= carga de excepciones ========= */
    public function cargarExcepciones(): void
    {
        $q = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId);

        if ($this->filtroExcepcionesDesde) {
            $q->whereDate('fecha', '>=', $this->filtroExcepcionesDesde);
        }
        if ($this->filtroExcepcionesHasta) {
            $q->whereDate('fecha', '<=', $this->filtroExcepcionesHasta);
        }

        $this->excepciones = $q->orderByDesc('fecha')
            ->limit(300)
            ->get()
            ->map(function (ExcepcionDisponibilidad $e) {
                return [
                    'id'          => $e->id,
                    'fecha'       => Carbon::parse($e->fecha)->format('Y-m-d'),
                    'bloqueado'   => (bool) $e->bloqueado,
                    'hora_desde'  => $e->hora_desde ? substr($e->hora_desde, 0, 5) : null,
                    'hora_hasta'  => $e->hora_hasta ? substr($e->hora_hasta, 0, 5) : null,
                    'motivo'      => $e->motivo,
                ];
            })
            ->toArray();
    }

    /** ========= alta/baja de excepciones ========= */

    public function agregarExcepcion(): void
    {
        // Normalizar entrada
        $data = [
            'profesional_id' => $this->profesionalId,
            'fecha'          => $this->nuevaExcepcion['fecha'] ?? null,
            'bloqueado'      => (bool) ($this->nuevaExcepcion['bloqueado'] ?? false),
            'hora_desde'     => $this->nuevaExcepcion['hora_desde'] ?: null,
            'hora_hasta'     => $this->nuevaExcepcion['hora_hasta'] ?: null,
            'motivo'         => $this->nuevaExcepcion['motivo'] ?: null,
        ];

        // Validaciones
        if (empty($data['fecha'])) {
            Notification::make()->title('La fecha es obligatoria.')->danger()->send();
            return;
        }

        if ($data['bloqueado'] === true) {
            // Día completo -> horas = null
            $data['hora_desde'] = null;
            $data['hora_hasta'] = null;

            if ($this->excepcionSolapa($data, null)) {
                Notification::make()
                    ->title('Ya existe un bloqueo de día completo en esa fecha.')
                    ->danger()->send();
                return;
            }

            ExcepcionDisponibilidad::create($data);
        } else {
            // Parcial
            if (!$data['hora_desde'] || !$data['hora_hasta']) {
                Notification::make()->title('Para excepción parcial, horas desde y hasta son obligatorias.')->danger()->send();
                return;
            }
            if ($data['hora_hasta'] <= $data['hora_desde']) {
                Notification::make()->title('La hora hasta debe ser mayor que la hora desde.')->danger()->send();
                return;
            }
            if ($this->excepcionSolapa($data, null)) {
                Notification::make()->title('El tramo se solapa con otra excepción o existe un día completo.')->danger()->send();
                return;
            }

            // Guardar con segundos
            $data['hora_desde'] .= ':00';
            $data['hora_hasta'] .= ':00';

            ExcepcionDisponibilidad::create($data);
        }

        Notification::make()->title('Excepción creada')->success()->send();

        // Reset inteligente del form:
        if ($this->mantenerFechaExcepcion) {
            $this->nuevaExcepcion = [
                'fecha'       => $this->nuevaExcepcion['fecha'],
                'bloqueado'   => true,
                'hora_desde'  => null,
                'hora_hasta'  => null,
                'motivo'      => null,
            ];
        } else {
            $this->nuevaExcepcion = [
                'fecha'       => null,
                'bloqueado'   => true,
                'hora_desde'  => null,
                'hora_hasta'  => null,
                'motivo'      => null,
            ];
        }

        $this->cargarExcepciones();
    }

    public function eliminarExcepcion(int $id): void
    {
        $ex = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId)
            ->where('id', $id)
            ->first();

        if (! $ex) {
            Notification::make()->title('Excepción no encontrada.')->danger()->send();
            return;
        }

        $ex->delete();

        Notification::make()->title('Excepción eliminada')->success()->send();

        $this->cargarExcepciones();
    }

    /** Filtros del listado de excepciones */
    public function aplicarFiltroExcepciones(): void
    {
        $this->cargarExcepciones();
    }

    public function limpiarFiltroExcepciones(): void
    {
        $this->filtroExcepcionesDesde = null;
        $this->filtroExcepcionesHasta = null;
        $this->cargarExcepciones();
    }

    /**
     * Chequea si $data solapa con otra excepción existente de la misma fecha.
     * Regla MVP (Opción A):
     * - Full-day (bloqueado=true y horas NULL): colisiona SOLO con otro full-day.
     * - Parcial: colisiona con full-day o con otra parcial que se solape.
     */
    protected function excepcionSolapa(array $data, ?int $excludeId = null): bool
    {
        if (empty($data['profesional_id']) || empty($data['fecha'])) {
            return false;
        }

        $base = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $data['profesional_id'])
            ->whereDate('fecha', $data['fecha'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        $bloqueado = (bool) ($data['bloqueado'] ?? false);
        $desde     = $data['hora_desde'] ?? null;
        $hasta     = $data['hora_hasta'] ?? null;

        // Full-day: solo impide si YA hay otro full-day ese día.
        if ($bloqueado) {
            return $base
                ->where('bloqueado', 1)
                ->whereNull('hora_desde')
                ->whereNull('hora_hasta')
                ->exists();
        }

        // Parcial: choca con full-day o con otra parcial que se solape.
        return $base->where(function ($q) use ($desde, $hasta) {
            $q->where(function ($q1) {
                // Conflicto con full-day
                $q1->where('bloqueado', 1)
                    ->whereNull('hora_desde')
                    ->whereNull('hora_hasta');
            })->orWhere(function ($q2) use ($desde, $hasta) {
                // Conflicto con otra parcial solapada
                $q2->where('bloqueado', 0)
                    ->whereNotNull('hora_desde')
                    ->whereNotNull('hora_hasta')
                    ->where('hora_desde', '<', $hasta)
                    ->where('hora_hasta', '>', $desde);
            });
        })->exists();
    }

    /** ================== BLOQUES ================== */

    /** Guarda SOLO el día indicado (ej: 2 = Martes) */
    public function guardarDia(int $dia): void
    {
        if (! array_key_exists($dia, $this->dias)) {
            Notification::make()->title('Día inválido.')->danger()->send();
            return;
        }

        $conf   = $this->estado[$dia] ?? [];
        $man    = $conf['maniana'] ?? ['enabled' => false];
        $tar    = $conf['tarde']   ?? ['enabled' => false];
        $consId = $conf['consultorio_id'] ?? null;

        // Validaciones de rango
        if (($man['enabled'] ?? false) && ! $this->validRange($man['desde'], $man['hasta'])) {
            Notification::make()->title("Mañana ({$this->dias[$dia]}): la hora hasta debe ser mayor que la hora desde.")->danger()->send();
            return;
        }
        if (($tar['enabled'] ?? false) && ! $this->validRange($tar['desde'], $tar['hasta'])) {
            Notification::make()->title("Tarde ({$this->dias[$dia]}): la hora hasta debe ser mayor que la hora desde.")->danger()->send();
            return;
        }
        if (($man['enabled'] ?? false) && ($tar['enabled'] ?? false)) {
            // Evitar solape entre mañana y tarde
            if (! ($man['hasta'] <= $tar['desde'] || $tar['hasta'] <= $man['desde'])) {
                Notification::make()->title("{$this->dias[$dia]}: los tramos mañana y tarde se solapan.")->danger()->send();
                return;
            }
        }

        // -------- Pre-chequeo: ¿hay turnos futuros que se solapen con los bloques actuales de ese día? --------
        $bloquesViejos = BloqueDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId)
            ->where('dia_semana', $dia)
            ->get(['hora_desde', 'hora_hasta']);

        if ($bloquesViejos->isNotEmpty()) {
            $tieneTurnos = Turno::query()
                ->where('profesional_id', $this->profesionalId)
                ->whereDate('fecha', '>=', now()->toDateString())
                // DAYOFWEEK(MySQL): 1=Dom, 2=Lun, ... 7=Sáb (ajuste domingo si habilitás)
                ->whereRaw('DAYOFWEEK(fecha) = ?', [$dia === 0 ? 1 : $dia + 1])
                ->where(function ($q) use ($bloquesViejos) {
                    foreach ($bloquesViejos as $b) {
                        $q->orWhere(function ($qq) use ($b) {
                            $qq->where('hora_desde', '<', $b->hora_hasta)
                                ->where('hora_hasta', '>', $b->hora_desde);
                        });
                    }
                })
                ->exists();

            if ($tieneTurnos) {
                Notification::make()
                    ->title('No se puede modificar este día')
                    ->body('Hay turnos asignados en los horarios actuales. Reprogramá o cancelá esos turnos antes de cambiar la disponibilidad.')
                    ->danger()->send();
                return;
            }
        }
        // ------------------------------------------------------------------------------------------------------

        try {
            DB::transaction(function () use ($dia, $man, $tar, $consId) {
                // Borrar existentes y crear los nuevos
                BloqueDisponibilidad::query()
                    ->where('profesional_id', $this->profesionalId)
                    ->where('dia_semana', $dia)
                    ->delete();

                $toInsert = [];

                if ($man['enabled'] ?? false) {
                    $toInsert[] = [
                        'profesional_id'   => $this->profesionalId,
                        'consultorio_id'   => $consId ?: null,
                        'dia_semana'       => $dia,
                        'hora_desde'       => $man['desde'] . ':00',
                        'hora_hasta'       => $man['hasta'] . ':00',
                        'duracion_minutos' => $this->duracion,
                        'activo'           => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }

                if ($tar['enabled'] ?? false) {
                    $toInsert[] = [
                        'profesional_id'   => $this->profesionalId,
                        'consultorio_id'   => $consId ?: null,
                        'dia_semana'       => $dia,
                        'hora_desde'       => $tar['desde'] . ':00',
                        'hora_hasta'       => $tar['hasta'] . ':00',
                        'duracion_minutos' => $this->duracion,
                        'activo'           => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }

                if (! empty($toInsert)) {
                    BloqueDisponibilidad::insert($toInsert);
                }
            });

            Notification::make()
                ->title("{$this->dias[$dia]} guardado")
                ->success()
                ->body('Los bloques se actualizaron correctamente.')
                ->send();

            // Recargar desde BD para reflejar lo guardado
            $this->cargarDesdeBD();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('No se pudo guardar')
                ->body('Ocurrió un error inesperado. Intentalo de nuevo.')
                ->danger()->send();
        }
    }

    /** Copia la configuración del $diaOrigen a TODOS los días visibles (Lunes..Sábado) */
    public function copiarATodos(int $diaOrigen): void
    {
        if (! array_key_exists($diaOrigen, $this->dias)) {
            Notification::make()->title('Día inválido.')->danger()->send();
            return;
        }

        $plantilla = $this->estado[$diaOrigen] ?? null;
        if (! $plantilla) {
            Notification::make()->title('Ese día no tiene configuración para copiar.')->warning()->send();
            return;
        }

        $man = $plantilla['maniana'] ?? ['enabled' => false];
        $tar = $plantilla['tarde']   ?? ['enabled' => false];

        // Validar la plantilla antes de propagar
        if (($man['enabled'] ?? false) && ! $this->validRange($man['desde'], $man['hasta'])) {
            Notification::make()->title("Plantilla mañana ({$this->dias[$diaOrigen]}): rango inválido.")->danger()->send();
            return;
        }
        if (($tar['enabled'] ?? false) && ! $this->validRange($tar['desde'], $tar['hasta'])) {
            Notification::make()->title("Plantilla tarde ({$this->dias[$diaOrigen]}): rango inválido.")->danger()->send();
            return;
        }
        if (($man['enabled'] ?? false) && ($tar['enabled'] ?? false)) {
            if (! ($man['hasta'] <= $tar['desde'] || $tar['hasta'] <= $man['desde'])) {
                Notification::make()->title("Plantilla {$this->dias[$diaOrigen]}: mañana y tarde se solapan.")->danger()->send();
                return;
            }
        }

        $consId = $plantilla['consultorio_id'] ?? null;

        $aplicados = [];
        $saltados  = [];

        foreach (array_keys($this->dias) as $dia) {
            try {
                DB::transaction(function () use ($dia, $plantilla, $consId, &$aplicados, &$saltados) {
                    // 1) Traigo bloques viejos del día
                    $bloquesViejos = BloqueDisponibilidad::query()
                        ->where('profesional_id', $this->profesionalId)
                        ->where('dia_semana', $dia)
                        ->get(['hora_desde', 'hora_hasta']);

                    if ($bloquesViejos->isNotEmpty()) {
                        // 2) ¿Hay turnos futuros que solapen con esos bloques?
                        $tieneTurnos = \App\Models\Turno::query()
                            ->where('profesional_id', $this->profesionalId)
                            ->whereDate('fecha', '>=', now()->toDateString())
                            // DAYOFWEEK(MySQL): 1=Dom, 2=Lun, ... 7=Sáb
                            ->whereRaw('DAYOFWEEK(fecha) = ?', [$dia === 0 ? 1 : $dia + 1])
                            ->where(function ($q) use ($bloquesViejos) {
                                foreach ($bloquesViejos as $b) {
                                    $q->orWhere(function ($qq) use ($b) {
                                        $qq->where('hora_desde', '<', $b->hora_hasta)
                                            ->where('hora_hasta', '>', $b->hora_desde);
                                    });
                                }
                            })
                            ->exists();

                        if ($tieneTurnos) {
                            $saltados[] = $this->dias[$dia];
                            // Corto esta mini-transacción para este día
                            throw new \RuntimeException('skip-day');
                        }
                    }

                    // 3) Borrar existentes del día y crear según plantilla
                    BloqueDisponibilidad::query()
                        ->where('profesional_id', $this->profesionalId)
                        ->where('dia_semana', $dia)
                        ->delete();

                    $toInsert = [];

                    if (($plantilla['maniana']['enabled'] ?? false)) {
                        $toInsert[] = [
                            'profesional_id'   => $this->profesionalId,
                            'consultorio_id'   => $consId ?: null,
                            'dia_semana'       => $dia,
                            'hora_desde'       => $plantilla['maniana']['desde'] . ':00',
                            'hora_hasta'       => $plantilla['maniana']['hasta'] . ':00',
                            'duracion_minutos' => $this->duracion,
                            'activo'           => true,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                    }

                    if (($plantilla['tarde']['enabled'] ?? false)) {
                        $toInsert[] = [
                            'profesional_id'   => $this->profesionalId,
                            'consultorio_id'   => $consId ?: null,
                            'dia_semana'       => $dia,
                            'hora_desde'       => $plantilla['tarde']['desde'] . ':00',
                            'hora_hasta'       => $plantilla['tarde']['hasta'] . ':00',
                            'duracion_minutos' => $this->duracion,
                            'activo'           => true,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                    }

                    if (! empty($toInsert)) {
                        BloqueDisponibilidad::insert($toInsert);
                    }

                    $aplicados[] = $this->dias[$dia];
                });
            } catch (\RuntimeException $e) {
                // si fue 'skip-day', ya lo contamos en $saltados
                if ($e->getMessage() !== 'skip-day') {
                    // otro error inesperado
                    $saltados[] = $this->dias[$dia];
                }
            }
        }

        // Notificación resumen
        if (!empty($aplicados) && empty($saltados)) {
            Notification::make()
                ->title('Copiado a todos los días')
                ->success()
                ->body('Se aplicó la configuración a: ' . implode(', ', $aplicados) . '.')
                ->send();
        } elseif (!empty($aplicados) && !empty($saltados)) {
            Notification::make()
                ->title('Copiado parcial')
                ->warning()
                ->body(
                    'Aplicado en: ' . implode(', ', $aplicados) .
                        '. Saltado (tienen turnos): ' . implode(', ', $saltados) . '.'
                )
                ->send();
        } else {
            Notification::make()
                ->title('No se copió la configuración')
                ->danger()
                ->body('Todos los días tenían turnos que impedían el cambio.')
                ->send();
        }

        $this->cargarDesdeBD();
    }

    /** Valida que hasta > desde (formatos HH:mm) */
    protected function validRange(string $desde, string $hasta): bool
    {
        return $hasta > $desde;
    }
}
