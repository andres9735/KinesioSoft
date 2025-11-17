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
    protected static ?int    $navigationSort  = 10;

    protected static string $view = 'filament.kinesiologa.pages.mi-agenda-semanal';

    /** Profesional que se está editando (por defecto: la kinesiologa logueada) */
    public int $profesionalId;

    /** Nombre y especialidad sólo para el encabezado */
    public string $profesionalNombre = '';
    public ?string $profesionalEspecialidad = null;

    /** Select de consultorios [id => nombre] */
    public array $consultorios = [];

    /** Duración global (min) aplicada a los bloques que se creen/actualicen desde esta pantalla */
    public int $duracion = 45;

    /** Estado UI por día (1..6=Lun..Sáb; 0=Dom si lo habilitás) */
    public array $estado = [];

    /** Horarios por defecto para la UI */
    public array $default = [
        'maniana' => ['desde' => '08:00', 'hasta' => '12:00'],
        'tarde'   => ['desde' => '16:00', 'hasta' => '20:00'],
    ];

    /** Días visibles */
    public array $dias = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        // 0 => 'Domingo',
    ];

    /* =================== Excepciones =================== */

    /** Listado de excepciones existentes */
    public array $excepciones = [];

    /** Form “nueva excepción” */
    public array $nuevaExcepcion = [
        'fecha'       => null,
        'bloqueado'   => true,   // día completo
        'hora_desde'  => null,
        'hora_hasta'  => null,
        'motivo'      => null,
    ];

    /** Mantener la fecha al agregar excepción */
    public bool $mantenerFechaExcepcion = true;

    /** Filtros para el listado de excepciones */
    public ?string $filtroExcepcionesDesde = null;
    public ?string $filtroExcepcionesHasta = null;

    /** Flag interno: cuando guardamos toda la semana, suprimimos toasts por fila */
    public bool $batchSaving = false;

    /* =================================================== */

    public static function canAccess(): bool
    {
        /** @var User|null $u */
        $u = Filament::auth()->user();
        return $u?->hasAnyRole(['Kinesiologa', 'Administrador']) ?? false;
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        // Profesional por defecto = usuario actual
        $this->profesionalId = $user->id;

        // Si es admin, puede editar a otra profesional con ?user_id=ID
        if ($user->hasRole('Administrador')) {
            $requestUserId = (int) request()->query('user_id', 0);
            if ($requestUserId > 0) {
                $target = User::query()->find($requestUserId);
                if ($target && $target->hasRole('Kinesiologa')) {
                    $this->profesionalId = (int) $target->id;
                }
            }
        }

        // Encabezado: nombre/especialidad (con fallbacks para no romper)
        $p = User::query()->find($this->profesionalId);
        $this->profesionalNombre       = $p?->name ?? ('Usuario #' . $this->profesionalId);
        $this->profesionalEspecialidad = $p?->especialidad ?? null;

        // Consultorios
        $this->consultorios = Consultorio::query()
            ->orderBy('nombre')
            ->pluck('nombre', 'id_consultorio')
            ->toArray();

        $this->cargarDesdeBD();
        $this->cargarExcepciones();
    }

    /** Construye el estado por día a partir de la BD */
    protected function cargarDesdeBD(): void
    {
        $baseDia = [
            'consultorio_id' => null,
            'maniana' => ['enabled' => true, 'desde' => $this->default['maniana']['desde'], 'hasta' => $this->default['maniana']['hasta']],
            'tarde'   => ['enabled' => true, 'desde' => $this->default['tarde']['desde'],   'hasta' => $this->default['tarde']['hasta']],
        ];

        $this->estado = [];
        foreach ($this->dias as $nro => $_) {
            $this->estado[$nro] = $baseDia;
        }

        $bloques = BloqueDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId)
            ->whereIn('dia_semana', array_keys($this->dias))
            ->where('activo', true)
            ->orderBy('dia_semana')
            ->orderBy('hora_desde')
            ->get();

        if ($bloques->isNotEmpty()) {
            $this->duracion = (int) ($bloques->first()->duracion_minutos ?: 45);
        }

        foreach ($bloques as $b) {
            $dia   = (int) $b->dia_semana;
            $desde = Carbon::createFromFormat('H:i:s', $b->hora_desde)->format('H:i');
            $hasta = Carbon::createFromFormat('H:i:s', $b->hora_hasta)->format('H:i');

            if ($this->estado[$dia]['consultorio_id'] === null) {
                $this->estado[$dia]['consultorio_id'] = $b->consultorio_id;
            }

            // Regla simple: hasta <= 13:00 => “mañana”, si no “tarde”
            if ($hasta <= '13:00') {
                $this->estado[$dia]['maniana'] = ['enabled' => true, 'desde' => $desde, 'hasta' => $hasta];
            } else {
                $this->estado[$dia]['tarde']   = ['enabled' => true, 'desde' => $desde, 'hasta' => $hasta];
            }
        }
    }

    /* =================== Excepciones =================== */

    public function cargarExcepciones(): void
    {
        $q = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId);

        if ($this->filtroExcepcionesDesde) $q->whereDate('fecha', '>=', $this->filtroExcepcionesDesde);
        if ($this->filtroExcepcionesHasta) $q->whereDate('fecha', '<=', $this->filtroExcepcionesHasta);

        $this->excepciones = $q->orderByDesc('fecha')
            ->limit(300)
            ->get()
            ->map(function (ExcepcionDisponibilidad $e) {
                return [
                    'id'         => $e->id,
                    'fecha'      => Carbon::parse($e->fecha)->format('Y-m-d'),
                    'bloqueado'  => (bool) $e->bloqueado,
                    'hora_desde' => $e->hora_desde ? substr($e->hora_desde, 0, 5) : null,
                    'hora_hasta' => $e->hora_hasta ? substr($e->hora_hasta, 0, 5) : null,
                    'motivo'     => $e->motivo,
                ];
            })
            ->toArray();
    }

    public function agregarExcepcion(): void
    {
        $data = [
            'profesional_id' => $this->profesionalId,
            'fecha'          => $this->nuevaExcepcion['fecha'] ?? null,
            'bloqueado'      => (bool) ($this->nuevaExcepcion['bloqueado'] ?? false),
            'hora_desde'     => $this->nuevaExcepcion['hora_desde'] ?: null,
            'hora_hasta'     => $this->nuevaExcepcion['hora_hasta'] ?: null,
            'motivo'         => $this->nuevaExcepcion['motivo'] ?: null,
        ];

        if (empty($data['fecha'])) {
            Notification::make()->title('La fecha es obligatoria.')->danger()->send();
            return;
        }

        if ($data['bloqueado']) {
            $data['hora_desde'] = null;
            $data['hora_hasta'] = null;

            if ($this->excepcionSolapa($data, null)) {
                Notification::make()->title('Ya existe un bloqueo de día completo en esa fecha.')->danger()->send();
                return;
            }

            ExcepcionDisponibilidad::create($data);
        } else {
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

            $data['hora_desde'] .= ':00';
            $data['hora_hasta'] .= ':00';
            ExcepcionDisponibilidad::create($data);
        }

        Notification::make()->title('Excepción creada')->success()->send();

        $this->nuevaExcepcion = [
            'fecha'       => $this->mantenerFechaExcepcion ? ($this->nuevaExcepcion['fecha'] ?? null) : null,
            'bloqueado'   => true,
            'hora_desde'  => null,
            'hora_hasta'  => null,
            'motivo'      => null,
        ];

        $this->cargarExcepciones();
    }

    public function eliminarExcepcion(int $id): void
    {
        $ex = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId)
            ->where('id', $id)
            ->first();

        if (!$ex) {
            Notification::make()->title('Excepción no encontrada.')->danger()->send();
            return;
        }

        $ex->delete();
        Notification::make()->title('Excepción eliminada')->success()->send();
        $this->cargarExcepciones();
    }

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

    protected function excepcionSolapa(array $data, ?int $excludeId = null): bool
    {
        if (empty($data['profesional_id']) || empty($data['fecha'])) return false;

        $base = ExcepcionDisponibilidad::query()
            ->where('profesional_id', $data['profesional_id'])
            ->whereDate('fecha', $data['fecha'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        $bloqueado = (bool) ($data['bloqueado'] ?? false);
        $desde     = $data['hora_desde'] ?? null;
        $hasta     = $data['hora_hasta'] ?? null;

        if ($bloqueado) {
            return $base->where('bloqueado', 1)->whereNull('hora_desde')->whereNull('hora_hasta')->exists();
        }

        return $base->where(function ($q) use ($desde, $hasta) {
            $q->where(function ($q1) {
                $q1->where('bloqueado', 1)->whereNull('hora_desde')->whereNull('hora_hasta');
            })->orWhere(function ($q2) use ($desde, $hasta) {
                $q2->where('bloqueado', 0)
                    ->whereNotNull('hora_desde')->whereNotNull('hora_hasta')
                    ->where('hora_desde', '<', $hasta)
                    ->where('hora_hasta', '>', $desde);
            });
        })->exists();
    }

    /* =================== Bloques (guardar) =================== */

    /** Guarda SOLO un día. Retorna true/false y respeta $batchSaving para silenciar toasts. */
    public function guardarDia(int $dia): bool
    {
        if (!array_key_exists($dia, $this->dias)) {
            if (!$this->batchSaving) Notification::make()->title('Día inválido.')->danger()->send();
            return false;
        }

        $conf   = $this->estado[$dia] ?? [];
        $man    = $conf['maniana'] ?? ['enabled' => false];
        $tar    = $conf['tarde']   ?? ['enabled' => false];
        $consId = $conf['consultorio_id'] ?? null;

        if (($man['enabled'] ?? false) && !$this->validRange($man['desde'], $man['hasta'])) {
            if (!$this->batchSaving) Notification::make()->title("Mañana ({$this->dias[$dia]}): la hora hasta debe ser mayor que la hora desde.")->danger()->send();
            return false;
        }
        if (($tar['enabled'] ?? false) && !$this->validRange($tar['desde'], $tar['hasta'])) {
            if (!$this->batchSaving) Notification::make()->title("Tarde ({$this->dias[$dia]}): la hora hasta debe ser mayor que la hora desde.")->danger()->send();
            return false;
        }
        if (($man['enabled'] ?? false) && ($tar['enabled'] ?? false)) {
            if (!($man['hasta'] <= $tar['desde'] || $tar['hasta'] <= $man['desde'])) {
                if (!$this->batchSaving) Notification::make()->title("{$this->dias[$dia]}: los tramos mañana y tarde se solapan.")->danger()->send();
                return false;
            }
        }

        // ¿Hay turnos futuros que se solapen con los bloques ACTUALES de ese día?
        $bloquesViejos = BloqueDisponibilidad::query()
            ->where('profesional_id', $this->profesionalId)
            ->where('dia_semana', $dia)
            ->get(['hora_desde', 'hora_hasta']);

        if ($bloquesViejos->isNotEmpty()) {
            $tieneTurnos = Turno::query()
                ->where('profesional_id', $this->profesionalId)
                ->whereDate('fecha', '>=', now()->toDateString())
                ->whereRaw('DAYOFWEEK(fecha) = ?', [$dia === 0 ? 1 : $dia + 1]) // MySQL: 1=Dom,2=Lun,…7=Sáb
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
                if (!$this->batchSaving) {
                    Notification::make()
                        ->title('No se puede modificar este día')
                        ->body('Hay turnos asignados en los horarios actuales. Reprogramá o cancelá esos turnos antes de cambiar la disponibilidad.')
                        ->danger()->send();
                }
                return false;
            }
        }

        $ok = false;

        try {
            DB::transaction(function () use ($dia, $man, $tar, $consId, &$ok) {
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

                if (!empty($toInsert)) {
                    BloqueDisponibilidad::insert($toInsert);
                }

                $ok = true;
            });

            if (!$this->batchSaving) {
                Notification::make()
                    ->title("{$this->dias[$dia]} guardado")
                    ->success()
                    ->body('Los bloques se actualizaron correctamente.')
                    ->send();
                $this->cargarDesdeBD();
            }
        } catch (\Throwable $e) {
            report($e);
            if (!$this->batchSaving) {
                Notification::make()
                    ->title('No se pudo guardar')
                    ->body('Ocurrió un error inesperado. Intentalo de nuevo.')
                    ->danger()->send();
            }
            $ok = false;
        }

        return $ok;
    }

    /** Copia configuración del $diaOrigen a todos los días (saltando días con turnos) */
    public function copiarATodos(int $diaOrigen): void
    {
        if (!array_key_exists($diaOrigen, $this->dias)) {
            Notification::make()->title('Día inválido.')->danger()->send();
            return;
        }

        $plantilla = $this->estado[$diaOrigen] ?? null;
        if (!$plantilla) {
            Notification::make()->title('Ese día no tiene configuración para copiar.')->warning()->send();
            return;
        }

        $man = $plantilla['maniana'] ?? ['enabled' => false];
        $tar = $plantilla['tarde']   ?? ['enabled' => false];

        if (($man['enabled'] ?? false) && !$this->validRange($man['desde'], $man['hasta'])) {
            Notification::make()->title("Plantilla mañana ({$this->dias[$diaOrigen]}): rango inválido.")->danger()->send();
            return;
        }
        if (($tar['enabled'] ?? false) && !$this->validRange($tar['desde'], $tar['hasta'])) {
            Notification::make()->title("Plantilla tarde ({$this->dias[$diaOrigen]}): rango inválido.")->danger()->send();
            return;
        }
        if (($man['enabled'] ?? false) && ($tar['enabled'] ?? false)) {
            if (!($man['hasta'] <= $tar['desde'] || $tar['hasta'] <= $man['desde'])) {
                Notification::make()->title("Plantilla {$this->dias[$diaOrigen]}: mañana y tarde se solapan.")->danger()->send();
                return;
            }
        }

        $consId    = $plantilla['consultorio_id'] ?? null;
        $aplicados = [];
        $saltados  = [];

        foreach (array_keys($this->dias) as $dia) {
            try {
                DB::transaction(function () use ($dia, $plantilla, $consId, &$aplicados, &$saltados) {
                    $bloquesViejos = BloqueDisponibilidad::query()
                        ->where('profesional_id', $this->profesionalId)
                        ->where('dia_semana', $dia)
                        ->get(['hora_desde', 'hora_hasta']);

                    if ($bloquesViejos->isNotEmpty()) {
                        $tieneTurnos = Turno::query()
                            ->where('profesional_id', $this->profesionalId)
                            ->whereDate('fecha', '>=', now()->toDateString())
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
                            throw new \RuntimeException('skip-day');
                        }
                    }

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

                    if (!empty($toInsert)) BloqueDisponibilidad::insert($toInsert);

                    $aplicados[] = $this->dias[$dia];
                });
            } catch (\RuntimeException $e) {
                if ($e->getMessage() !== 'skip-day') $saltados[] = $this->dias[$dia];
            } catch (\Throwable $e) {
                report($e);
                $saltados[] = $this->dias[$dia];
            }
        }

        if (!empty($aplicados) && empty($saltados)) {
            Notification::make()->title('Copiado a todos los días')->success()->body('Se aplicó a: ' . implode(', ', $aplicados) . '.')->send();
        } elseif (!empty($aplicados) && !empty($saltados)) {
            Notification::make()->title('Copiado parcial')->warning()->body('Aplicado en: ' . implode(', ', $aplicados) . '.  Saltados (tienen turnos): ' . implode(', ', $saltados) . '.')->send();
        } else {
            Notification::make()->title('No se copió la configuración')->danger()->body('Todos los días tenían turnos que impedían el cambio.')->send();
        }

        $this->cargarDesdeBD();
    }

    /** Guarda toda la semana en una pasada (salta los días que no se puedan) */
    public function guardarSemana(): void
    {
        $this->batchSaving = true;

        $okDays   = [];
        $failDays = [];

        foreach ($this->dias as $dia => $nombre) {
            $ok = $this->guardarDia($dia);
            if ($ok) $okDays[] = $nombre;
            else $failDays[] = $nombre;
        }

        $this->batchSaving = false;
        $this->cargarDesdeBD();

        if ($okDays && !$failDays) {
            Notification::make()->title('Semana guardada')->success()->body('Se guardaron: ' . implode(', ', $okDays) . '.')->send();
        } elseif ($okDays && $failDays) {
            Notification::make()->title('Guardado parcial')->warning()->body('Guardados: ' . implode(', ', $okDays) . '.  Saltados: ' . implode(', ', $failDays) . '.')->send();
        } else {
            Notification::make()->title('No se guardó la semana')->danger()->body('Todos los días fueron bloqueados por turnos o validaciones.')->send();
        }
    }

    protected function validRange(string $desde, string $hasta): bool
    {
        return $hasta > $desde;
    }
}
