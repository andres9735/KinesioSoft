<?php

namespace App\Http\Controllers;

use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AgendaController extends Controller
{
    private const FILTROS_BLOQUEOS = [
        'proximos',
        'en_curso',
        'pasados',
        'anulados',
        'todos',
    ];

    public function __construct(
        private readonly AgendaQueryService $queryService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', ConfiguracionAgenda::class);
        Gate::authorize('viewAny', BloqueoAgenda::class);

        $kinesiologa = $this->kinesiologaAutenticada($request);
        $ahora = $this->reloj->ahora();
        $hoy = $this->reloj->hoy($ahora);
        $filtroBloqueos = $this->normalizarFiltro($request->query('filtro'));
        $configuracionVigente = $this->queryService
            ->configuracionAplicableEn($kinesiologa, $hoy);
        $configuracionesFuturas = $this->queryService
            ->futurasEn($kinesiologa, $hoy)
            ->with(['franjasHorarias' => fn ($query) => $query
                ->orderBy('dia_semana')
                ->orderBy('hora_inicio')
                ->orderBy('hora_fin')
                ->orderBy('id')])
            ->get();
        $proximaConfiguracion = $configuracionesFuturas->first();
        $hayAntecedentes = $this->queryService
            ->configuraciones($kinesiologa)
            ->exists();
        $cantidadHistorial = $this->queryService
            ->historialConfiguracionesEn($kinesiologa, $hoy)
            ->count();
        $cantidadBloqueosEnCurso = $this->queryService
            ->bloqueosEnCursoEn($kinesiologa, $ahora)
            ->count();
        $hayBloqueos = $this->queryService
            ->bloqueosTodos($kinesiologa)
            ->exists();
        $bloqueos = $this->queryBloqueos($kinesiologa, $filtroBloqueos, $ahora)
            ->paginate(10, ['*'], 'bloqueos_page')
            ->appends(['filtro' => $filtroBloqueos]);

        if ($bloqueos->currentPage() > $bloqueos->lastPage()) {
            return redirect()->route('turnos.agenda.index', [
                'filtro' => $filtroBloqueos,
                'bloqueos_page' => $bloqueos->lastPage(),
            ]);
        }

        $accionesConfiguracionesFuturas = $configuracionesFuturas
            ->mapWithKeys(fn (ConfiguracionAgenda $configuracion): array => [
                $configuracion->getKey() => [
                    'puede_editar' => Gate::allows('update', $configuracion),
                    'puede_anular' => Gate::allows('anular', $configuracion),
                ],
            ]);

        return view('agenda.index', [
            'ahora' => $ahora,
            'hoy' => $hoy,
            'configuracionVigente' => $configuracionVigente,
            'configuracionesFuturas' => $configuracionesFuturas,
            'proximaConfiguracion' => $proximaConfiguracion,
            'accionesConfiguracionesFuturas' => $accionesConfiguracionesFuturas,
            'hayAntecedentes' => $hayAntecedentes,
            'cantidadHistorial' => $cantidadHistorial,
            'puedeCambiarDuracion' => $configuracionVigente !== null,
            'bloqueos' => $bloqueos,
            'filtroBloqueos' => $filtroBloqueos,
            'filtrosBloqueos' => self::FILTROS_BLOQUEOS,
            'cantidadBloqueosEnCurso' => $cantidadBloqueosEnCurso,
            'hayBloqueos' => $hayBloqueos,
        ]);
    }

    private function queryBloqueos(
        Kinesiologa $kinesiologa,
        string $filtro,
        mixed $ahora
    ) {
        return match ($filtro) {
            'en_curso' => $this->queryService->bloqueosEnCursoEn($kinesiologa, $ahora),
            'pasados' => $this->queryService->bloqueosPasadosEn($kinesiologa, $ahora),
            'anulados' => $this->queryService->bloqueosAnulados($kinesiologa),
            'todos' => $this->queryService->bloqueosTodos($kinesiologa),
            default => $this->queryService->bloqueosProximosEn($kinesiologa, $ahora),
        };
    }

    private function normalizarFiltro(mixed $filtro): string
    {
        return is_string($filtro) && in_array($filtro, self::FILTROS_BLOQUEOS, true)
            ? $filtro
            : 'proximos';
    }

    private function kinesiologaAutenticada(Request $request): Kinesiologa
    {
        /** @var User|null $user */
        $user = $request->user();
        $kinesiologa = $user?->kinesiologa;

        abort_unless($kinesiologa instanceof Kinesiologa, 403);

        return $kinesiologa;
    }
}
