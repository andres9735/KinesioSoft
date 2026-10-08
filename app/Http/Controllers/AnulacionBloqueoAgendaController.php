<?php

namespace App\Http\Controllers;

use App\Actions\Agenda\AnularBloqueoAgendaAction;
use App\Exceptions\Agenda\BloqueoAgendaNoElegibleException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Http\Requests\Agenda\AnularBloqueoAgendaRequest;
use App\Models\BloqueoAgenda;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class AnulacionBloqueoAgendaController extends Controller
{
    private const FILTROS_BLOQUEOS = [
        'proximos',
        'en_curso',
        'pasados',
        'anulados',
        'todos',
    ];

    public function __construct(
        private readonly AnularBloqueoAgendaAction $anularAction,
        private readonly AgendaQueryService $queryService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function create(
        Request $request,
        BloqueoAgenda $bloqueo
    ): View {
        Gate::authorize('anular', $bloqueo);

        $ahora = $this->reloj->ahora();

        $otrosBloqueos = $this->queryService
            ->bloqueosSuperpuestosCon(
                $bloqueo->kinesiologa,
                $bloqueo->inicio_at,
                $bloqueo->fin_at,
                $bloqueo->getKey()
            )
            ->get();

        return view('agenda.bloqueos.anular', [
            'bloqueo' => $bloqueo,
            'estado' => $bloqueo->estadoEn($ahora),
            'snapshot' => $this->snapshotService->generarParaBloqueo($bloqueo),
            'otrosBloqueosSuperpuestos' => $otrosBloqueos,
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    public function store(
        AnularBloqueoAgendaRequest $request,
        BloqueoAgenda $bloqueo
    ): RedirectResponse {
        $contexto = $this->contextoRetorno($request);

        try {
            $this->anularAction->ejecutar(
                $request->user(),
                $bloqueo,
                $request->validated()
            );
        } catch (BloqueoAgendaNoElegibleException $exception) {
            return redirect()
                ->route(
                    'turnos.agenda.bloqueos.show',
                    [
                        'bloqueo' => $bloqueo,
                        ...$contexto,
                    ]
                )
                ->with('error', $exception->getMessage());
        } catch (SnapshotAgendaDesactualizadoException $exception) {
            $bloqueo->refresh();

            $route = Gate::allows('anular', $bloqueo)
                ? 'turnos.agenda.bloqueos.anular.create'
                : 'turnos.agenda.bloqueos.show';

            return redirect()
                ->route(
                    $route,
                    [
                        'bloqueo' => $bloqueo,
                        ...$contexto,
                    ]
                )
                ->withInput()
                ->with('error', $this->mensajeSnapshot());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route(
                    'turnos.agenda.bloqueos.anular.create',
                    [
                        'bloqueo' => $bloqueo,
                        ...$contexto,
                    ]
                )
                ->withErrors([
                    'bloqueo' => $exception->getMessage(),
                ])
                ->withInput();
        }

        return redirect()
            ->route(
                'turnos.agenda.index',
                array_filter(
                    [
                        'filtro' => $contexto['filtro'],
                        'bloqueos_page' => $contexto['bloqueos_page'] ?? null,
                    ],
                    fn (mixed $valor): bool => $valor !== null
                )
            )
            ->with(
                'success',
                'Bloqueo anulado correctamente.'
            );
    }

    /** @return array{origen: string, filtro: string, bloqueos_page?: int} */
    private function contextoRetorno(Request $request): array
    {
        $origen = $request->input('origen');

        $origen = is_string($origen)
            && in_array(
                $origen,
                ['dashboard', 'detalle', 'bloqueos'],
                true
            )
            ? $origen
            : 'bloqueos';

        $filtro = $request->input('filtro');

        $filtro = is_string($filtro)
            && in_array(
                $filtro,
                self::FILTROS_BLOQUEOS,
                true
            )
            ? $filtro
            : 'proximos';

        $contexto = [
            'origen' => $origen,
            'filtro' => $filtro,
        ];

        $page = filter_var(
            $request->input('bloqueos_page'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($page !== false) {
            $contexto['bloqueos_page'] = $page;
        }

        return $contexto;
    }

    private function mensajeSnapshot(): string
    {
        return 'La información cambió desde que abriste esta pantalla. Revisá el estado actual antes de confirmar nuevamente.';
    }
}
