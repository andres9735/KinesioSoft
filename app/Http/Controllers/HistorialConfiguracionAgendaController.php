<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HistorialConfiguracionAgendaController extends Controller
{
    public function __construct(
        private readonly AgendaQueryService $queryService,
        private readonly RelojAplicacion $reloj
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', ConfiguracionAgenda::class);

        $kinesiologa = $this->kinesiologaAutenticada($request);
        $hoy = $this->reloj->hoy();
        $configuraciones = $this->queryService
            ->historialConfiguracionesEn($kinesiologa, $hoy)
            ->withCount('franjasHorarias')
            ->paginate(10);

        if ($configuraciones->currentPage() > $configuraciones->lastPage()) {
            return redirect()->route('turnos.agenda.configuraciones.historial', [
                'page' => $configuraciones->lastPage(),
            ]);
        }

        return view('agenda.configuraciones.historial', [
            'configuraciones' => $configuraciones,
            'hoy' => $hoy,
        ]);
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
