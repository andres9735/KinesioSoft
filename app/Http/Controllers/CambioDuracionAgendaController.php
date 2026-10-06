<?php

namespace App\Http\Controllers;

use App\Actions\Agenda\CambiarDuracionAgendaAction;
use App\Exceptions\Agenda\ConfiguracionAgendaNoElegibleException;
use App\Exceptions\Agenda\ConflictoTemporalAgendaException;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Http\Requests\Agenda\StoreCambioDuracionAgendaRequest;
use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class CambioDuracionAgendaController extends Controller
{
    public function __construct(
        private readonly CambiarDuracionAgendaAction $cambiarDuracionAction,
        private readonly AgendaQueryService $queryService,
        private readonly AgendaSnapshotService $snapshotService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('cambiarDuracion', ConfiguracionAgenda::class);

        $kinesiologa = $this->kinesiologaAutenticada($request);
        $hoy = $this->reloj->hoy();
        $vigenciaDesde = $this->fechaEfecto($request, $hoy);
        $origen = $this->queryService->configuracionAplicableEn(
            $kinesiologa,
            $vigenciaDesde->subDay()
        );

        if ($origen === null) {
            return redirect()
                ->route('turnos.agenda.index')
                ->with('error', 'No existe una configuraciÃ³n de origen aplicable para conservar su matriz. Primero registrÃ¡ una configuraciÃ³n completa.');
        }

        $sucesora = $this->queryService->sucesoraDe($kinesiologa, $vigenciaDesde);

        return view('agenda.configuraciones.cambiar-duracion', [
            'configuracionOrigen' => $origen,
            'duracionActual' => (int) $origen->duracion_turno_minutos,
            'snapshot' => $this->snapshotService->generarParaConfiguracion($origen),
            'fechaMinima' => $hoy->addDay(),
            'vigenciaDesde' => $vigenciaDesde,
            'vigenciaHastaDerivada' => $sucesora?->vigencia_desde->subDay(),
        ]);
    }

    public function store(StoreCambioDuracionAgendaRequest $request): RedirectResponse
    {
        try {
            $this->cambiarDuracionAction->ejecutar($request->user(), $request->validated());
        } catch (SnapshotAgendaDesactualizadoException $exception) {
            return redirect()
                ->route('turnos.agenda.duracion.create', [
                    'vigencia_desde' => $request->input('vigencia_desde'),
                ])
                ->withInput()
                ->with('error', $this->mensajeSnapshot());
        } catch (ConfiguracionAgendaNoElegibleException|ConflictoTemporalAgendaException|InvalidArgumentException $exception) {
            return redirect()
                ->route('turnos.agenda.duracion.create', [
                    'vigencia_desde' => $request->input('vigencia_desde'),
                ])
                ->withErrors(['agenda' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('turnos.agenda.index')
            ->with('success', 'Cambio de duraciÃ³n programado correctamente.');
    }

    private function fechaEfecto(Request $request, CarbonImmutable $hoy): CarbonImmutable
    {
        $valor = $request->query('vigencia_desde');

        if (! is_string($valor)) {
            return $hoy->addDay();
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor, config('app.timezone'));
        } catch (Throwable) {
            return $hoy->addDay();
        }

        return $fecha->format('Y-m-d') === $valor && $fecha->gt($hoy)
            ? $fecha
            : $hoy->addDay();
    }

    private function kinesiologaAutenticada(Request $request): Kinesiologa
    {
        /** @var User|null $user */
        $user = $request->user();
        $kinesiologa = $user?->kinesiologa;

        abort_unless($kinesiologa instanceof Kinesiologa, 403);

        return $kinesiologa;
    }

    private function mensajeSnapshot(): string
    {
        return 'La informaciÃ³n cambiÃ³ desde que abriste esta pantalla. RevisÃ¡ el estado actual antes de confirmar nuevamente.';
    }
}
