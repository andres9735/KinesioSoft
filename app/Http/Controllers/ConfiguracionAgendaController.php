<?php

namespace App\Http\Controllers;

use App\Actions\Agenda\ActualizarConfiguracionAgendaFuturaAction;
use App\Actions\Agenda\CrearConfiguracionAgendaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Http\Requests\Agenda\StoreConfiguracionAgendaRequest;
use App\Http\Requests\Agenda\UpdateConfiguracionAgendaRequest;
use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ConfiguracionAgendaController extends Controller
{
    public function __construct(
        private readonly CrearConfiguracionAgendaAction $crearAction,
        private readonly ActualizarConfiguracionAgendaFuturaAction $actualizarAction,
        private readonly AgendaQueryService $queryService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function create(Request $request): View
    {
        Gate::authorize('create', ConfiguracionAgenda::class);

        $kinesiologa = $this->kinesiologaAutenticada($request);
        $hoy = $this->reloj->hoy();
        $esPrimeraConfiguracion = ! $this->queryService
            ->configuraciones($kinesiologa)
            ->exists();

        return view('agenda.configuraciones.create', [
            'hoy' => $hoy,
            'esPrimeraConfiguracion' => $esPrimeraConfiguracion,
            'defaults' => $esPrimeraConfiguracion ? $this->defaultsPrimeraConfiguracion() : null,
        ]);
    }

    public function store(StoreConfiguracionAgendaRequest $request): RedirectResponse
    {
        try {
            $this->crearAction->ejecutar($request->user(), $request->validated());
        } catch (ConflictoTemporalAgendaException|ConfiguracionAgendaNoElegibleException|InvalidArgumentException $exception) {
            return redirect()
                ->route('turnos.agenda.configuraciones.create')
                ->withErrors(['agenda' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('turnos.agenda.index')
            ->with('success', 'Configuración registrada correctamente.');
    }

    public function show(Request $request, ConfiguracionAgenda $configuracion): View
    {
        Gate::authorize('view', $configuracion);

        $hoy = $this->reloj->hoy();
        $this->cargarFranjas($configuracion);
        $proxima = $this->queryService->proximaEn($configuracion->kinesiologa, $hoy);

        return view('agenda.configuraciones.show', [
            'configuracion' => $configuracion,
            'estado' => $configuracion->estadoEn($hoy),
            'esProxima' => $proxima?->is($configuracion) ?? false,
            'puedeEditar' => Gate::allows('update', $configuracion),
            'puedeAnular' => Gate::allows('anular', $configuracion),
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    public function edit(Request $request, ConfiguracionAgenda $configuracion): View
    {
        Gate::authorize('update', $configuracion);

        $this->cargarFranjas($configuracion);

        return view('agenda.configuraciones.edit', [
            'configuracion' => $configuracion,
            'snapshot' => $this->snapshotService->generarParaConfiguracion($configuracion),
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    public function update(
        UpdateConfiguracionAgendaRequest $request,
        ConfiguracionAgenda $configuracion
    ): RedirectResponse {
        $contexto = $this->contextoRetorno($request);

        try {
            $actualizada = $this->actualizarAction->ejecutar(
                $request->user(),
                $configuracion,
                $request->validated()
            );
        } catch (ConfiguracionAgendaNoElegibleException $exception) {
            return redirect()
                ->route('turnos.agenda.configuraciones.show', $configuracion)
                ->with('error', $exception->getMessage());
        } catch (SnapshotAgendaDesactualizadoException $exception) {
            return redirect()
                ->route('turnos.agenda.configuraciones.edit', [
                    'configuracion' => $configuracion,
                    ...$contexto,
                ])
                ->withInput()
                ->with('error', $this->mensajeSnapshot());
        } catch (ConflictoTemporalAgendaException|InvalidArgumentException $exception) {
            return redirect()
                ->route('turnos.agenda.configuraciones.edit', [
                    'configuracion' => $configuracion,
                    ...$contexto,
                ])
                ->withErrors(['agenda' => $exception->getMessage()])
                ->withInput();
        }

        $destino = $contexto['origen'] === 'detalle'
            ? redirect()->route('turnos.agenda.configuraciones.show', $actualizada)
            : redirect()->route('turnos.agenda.index');

        return $destino->with('success', 'Configuración futura actualizada correctamente.');
    }

    private function cargarFranjas(ConfiguracionAgenda $configuracion): void
    {
        $configuracion->load(['franjasHorarias' => fn ($query) => $query
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->orderBy('hora_fin')
            ->orderBy('id')]);
    }

    /** @return array{origen: string, page?: int} */
    private function contextoRetorno(Request $request): array
    {
        $origen = $request->input('origen');
        $origen = is_string($origen) && in_array($origen, ['dashboard', 'detalle', 'historial'], true)
            ? $origen
            : 'dashboard';

        $contexto = ['origen' => $origen];

        $page = filter_var($request->input('page'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($origen === 'historial' && $page !== false) {
            $contexto['page'] = $page;
        }

        return $contexto;
    }

    private function kinesiologaAutenticada(Request $request): Kinesiologa
    {
        /** @var User|null $user */
        $user = $request->user();
        $kinesiologa = $user?->kinesiologa;

        abort_unless($kinesiologa instanceof Kinesiologa, 403);

        return $kinesiologa;
    }

    /** @return array<string, mixed> */
    private function defaultsPrimeraConfiguracion(): array
    {
        return [
            'duracion_turno_minutos' => 45,
            'franjas' => collect(range(1, 5))->map(fn (int $dia): array => [
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fin' => '17:00',
            ])->all(),
        ];
    }

    private function mensajeSnapshot(): string
    {
        return 'La información cambió desde que abriste esta pantalla. Revisá el estado actual antes de confirmar nuevamente.';
    }
}
