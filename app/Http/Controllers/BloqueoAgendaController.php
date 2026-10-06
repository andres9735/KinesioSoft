<?php

namespace App\Http\Controllers;

use App\Actions\Agenda\RegistrarBloqueoAgendaAction;
use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Exceptions\Agenda\BloqueoAgendaNoElegibleException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Http\Requests\Agenda\RevisarBloqueoAgendaRequest;
use App\Http\Requests\Agenda\StoreBloqueoAgendaRequest;
use App\Models\BloqueoAgenda;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class BloqueoAgendaController extends Controller
{
    private const FILTROS_BLOQUEOS = [
        'proximos',
        'en_curso',
        'pasados',
        'anulados',
        'todos',
    ];

    public function __construct(
        private readonly RevisarBloqueoAgendaAction $revisarAction,
        private readonly RegistrarBloqueoAgendaAction $registrarAction,
        private readonly AgendaQueryService $queryService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function create(Request $request): View
    {
        Gate::authorize('create', BloqueoAgenda::class);

        return view('agenda.bloqueos.create', [
            'revision' => null,
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    public function review(RevisarBloqueoAgendaRequest $request): View|RedirectResponse
    {
        try {
            $revision = $this->revisarAction->ejecutar(
                $request->user(),
                $request->validated()
            );
        } catch (BloqueoAgendaNoElegibleException|InvalidArgumentException $exception) {
            return redirect()
                ->route('turnos.agenda.bloqueos.create')
                ->withErrors(['bloqueo' => $exception->getMessage()])
                ->withInput();
        }

        return view('agenda.bloqueos.create', [
            'revision' => $revision,
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    public function store(StoreBloqueoAgendaRequest $request): RedirectResponse|View
    {
        $data = $request->validated();

        try {
            $bloqueo = $this->registrarAction->ejecutar($request->user(), $data);
        } catch (SnapshotAgendaDesactualizadoException $exception) {
            try {
                $revision = $this->revisarAction->ejecutar($request->user(), $data);
            } catch (BloqueoAgendaNoElegibleException|InvalidArgumentException $revisionException) {
                return redirect()
                    ->route('turnos.agenda.bloqueos.create')
                    ->withErrors(['bloqueo' => $revisionException->getMessage()])
                    ->withInput();
            }

            session()->flash(
                'error',
                'La agenda cambió desde la revisión. Revisá nuevamente las advertencias antes de confirmar.'
            );

            return view('agenda.bloqueos.create', [
                'revision' => $revision,
                'contextoRetorno' => $this->contextoRetorno($request),
            ]);
        } catch (BloqueoAgendaNoElegibleException|InvalidArgumentException $exception) {
            return redirect()
                ->route('turnos.agenda.bloqueos.create')
                ->withErrors(['bloqueo' => $exception->getMessage()])
                ->withInput();
        }

        $filtro = $bloqueo->estadoEn($this->reloj->ahora()) === BloqueoAgenda::ESTADO_EN_CURSO
            ? 'en_curso'
            : 'proximos';

        return redirect()
            ->route('turnos.agenda.index', ['filtro' => $filtro])
            ->with('success', 'Bloqueo registrado correctamente.');
    }

    public function show(Request $request, BloqueoAgenda $bloqueo): View
    {
        Gate::authorize('view', $bloqueo);

        $ahora = $this->reloj->ahora();
        $otrosBloqueos = $this->queryService
            ->bloqueosSuperpuestosCon(
                $bloqueo->kinesiologa,
                $bloqueo->inicio_at,
                $bloqueo->fin_at,
                $bloqueo->getKey()
            )
            ->get();

        return view('agenda.bloqueos.show', [
            'bloqueo' => $bloqueo,
            'estado' => $bloqueo->estadoEn($ahora),
            'otrosBloqueosSuperpuestos' => $otrosBloqueos,
            'puedeAnular' => Gate::allows('anular', $bloqueo),
            'contextoRetorno' => $this->contextoRetorno($request),
        ]);
    }

    /** @return array{origen: string, filtro: string, bloqueos_page?: int} */
    private function contextoRetorno(Request $request): array
    {
        $origen = $request->input('origen');
        $origen = is_string($origen) && in_array($origen, ['dashboard', 'detalle', 'bloqueos'], true)
            ? $origen
            : 'bloqueos';
        $filtro = $request->input('filtro');
        $filtro = is_string($filtro) && in_array($filtro, self::FILTROS_BLOQUEOS, true)
            ? $filtro
            : 'proximos';
        $contexto = ['origen' => $origen, 'filtro' => $filtro];
        $page = filter_var($request->input('bloqueos_page'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($page !== false) {
            $contexto['bloqueos_page'] = $page;
        }

        return $contexto;
    }
}
