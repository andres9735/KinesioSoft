<?php

namespace App\Http\Controllers;

use App\Actions\Agenda\AnularConfiguracionAgendaFuturaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Http\Requests\Agenda\AnularConfiguracionAgendaRequest;
use App\Models\ConfiguracionAgenda;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class AnulacionConfiguracionAgendaController extends Controller
{
    public function __construct(
        private readonly AnularConfiguracionAgendaFuturaAction $anularAction,
        private readonly AgendaQueryService $queryService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function create(Request $request, ConfiguracionAgenda $configuracion): View
    {
        Gate::authorize('anular', $configuracion);

        $this->cargarFranjas($configuracion);
        $hoy = $this->reloj->hoy();
        $predecesora = $this->queryService->predecesoraDe(
            $configuracion->kinesiologa,
            $configuracion->vigencia_desde,
            $configuracion->getKey()
        );
        $sucesora = $this->queryService->sucesoraDe(
            $configuracion->kinesiologa,
            $configuracion->vigencia_desde,
            $configuracion->getKey()
        );
        $predecesoraVinculada = $predecesora !== null
            && $predecesora->vigencia_hasta?->toDateString()
                === $configuracion->vigencia_desde->subDay()->toDateString();

        return view('agenda.configuraciones.anular', [
            'configuracion' => $configuracion,
            'estado' => $configuracion->estadoEn($hoy),
            'snapshot' => $this->snapshotService->generarParaConfiguracion($configuracion),
            'predecesora' => $predecesora,
            'sucesora' => $sucesora,
            'predecesoraVinculada' => $predecesoraVinculada,
            'vigenciaHastaProyectada' => $predecesoraVinculada
                ? $sucesora?->vigencia_desde->subDay()
                : $predecesora?->vigencia_hasta,
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    public function store(
        AnularConfiguracionAgendaRequest $request,
        ConfiguracionAgenda $configuracion
    ): RedirectResponse {
        $contexto = $this->contextoRetorno($request);

        try {
            $this->anularAction->ejecutar(
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
                ->route('turnos.agenda.configuraciones.anular.create', [
                    'configuracion' => $configuracion,
                    ...$contexto,
                ])
                ->withInput()
                ->with('error', $this->mensajeSnapshot());
        } catch (ConflictoTemporalAgendaException|InvalidArgumentException $exception) {
            return redirect()
                ->route('turnos.agenda.configuraciones.anular.create', [
                    'configuracion' => $configuracion,
                    ...$contexto,
                ])
                ->withErrors(['agenda' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('turnos.agenda.index')
            ->with('success', 'ConfiguraciÃ³n futura anulada y continuidad recalculada.');
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

    private function mensajeSnapshot(): string
    {
        return 'La informaciÃ³n cambiÃ³ desde que abriste esta pantalla. RevisÃ¡ el estado actual antes de confirmar nuevamente.';
    }
}
