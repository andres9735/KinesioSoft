<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarTecnicaTerapeuticaAction;
use App\Actions\Catalogos\CambiarEstadoTecnicaTerapeuticaAction;
use App\Actions\Catalogos\CrearTecnicaTerapeuticaAction;
use App\Enums\SistemaOrigenTecnicaTerapeuticaEnum;
use App\Http\Requests\StoreTecnicaTerapeuticaRequest;
use App\Http\Requests\UpdateTecnicaTerapeuticaRequest;
use App\Models\TecnicaTerapeutica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TecnicaTerapeuticaController extends Controller
{
    public function __construct(
        private readonly CrearTecnicaTerapeuticaAction $crearTecnicaTerapeuticaAction,
        private readonly ActualizarTecnicaTerapeuticaAction $actualizarTecnicaTerapeuticaAction,
        private readonly CambiarEstadoTecnicaTerapeuticaAction $cambiarEstadoTecnicaTerapeuticaAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TecnicaTerapeutica::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $tecnicas = TecnicaTerapeutica::query()
            ->when($q !== '', fn ($query) => $query->where(
                fn ($busqueda) => $busqueda
                    ->where('codigo', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
            ))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('catalogos.tecnicas-terapeuticas.index', compact('tecnicas', 'q', 'estado'));
    }

    public function create(): View
    {
        Gate::authorize('create', TecnicaTerapeutica::class);

        $sistemasOrigen = SistemaOrigenTecnicaTerapeuticaEnum::cases();

        return view('catalogos.tecnicas-terapeuticas.create', compact('sistemasOrigen'));
    }

    public function store(StoreTecnicaTerapeuticaRequest $request): RedirectResponse
    {
        Gate::authorize('create', TecnicaTerapeutica::class);

        $this->crearTecnicaTerapeuticaAction->ejecutar($request->validated());

        return redirect()
            ->route('catalogos.tecnicas.index')
            ->with('success', 'Técnica terapéutica creada correctamente.');
    }

    public function show(TecnicaTerapeutica $tecnicaTerapeutica): View
    {
        Gate::authorize('view', $tecnicaTerapeutica);

        return view('catalogos.tecnicas-terapeuticas.show', compact('tecnicaTerapeutica'));
    }

    public function edit(TecnicaTerapeutica $tecnicaTerapeutica): View
    {
        Gate::authorize('update', $tecnicaTerapeutica);

        $sistemasOrigen = SistemaOrigenTecnicaTerapeuticaEnum::cases();

        return view('catalogos.tecnicas-terapeuticas.edit', compact('tecnicaTerapeutica', 'sistemasOrigen'));
    }

    public function update(
        UpdateTecnicaTerapeuticaRequest $request,
        TecnicaTerapeutica $tecnicaTerapeutica
    ): RedirectResponse {
        Gate::authorize('update', $tecnicaTerapeutica);

        $this->actualizarTecnicaTerapeuticaAction->ejecutar($tecnicaTerapeutica, $request->validated());

        return redirect()
            ->route('catalogos.tecnicas.index')
            ->with('success', 'Técnica terapéutica actualizada correctamente.');
    }

    public function inactivar(TecnicaTerapeutica $tecnicaTerapeutica): RedirectResponse
    {
        Gate::authorize('inactivar', $tecnicaTerapeutica);

        $tecnicaActualizado = $this->cambiarEstadoTecnicaTerapeuticaAction->ejecutar(
            $tecnicaTerapeutica,
            false
        );

        return redirect()
            ->route('catalogos.tecnicas.index')
            ->with(
                $tecnicaActualizado->wasChanged('activo') ? 'success' : 'warning',
                $tecnicaActualizado->wasChanged('activo')
                    ? 'Técnica terapéutica inactivada correctamente.'
                    : 'La técnica terapéutica ya se encontraba inactiva.'
            );
    }

    public function reactivar(TecnicaTerapeutica $tecnicaTerapeutica): RedirectResponse
    {
        Gate::authorize('reactivar', $tecnicaTerapeutica);

        $tecnicaActualizado = $this->cambiarEstadoTecnicaTerapeuticaAction->ejecutar(
            $tecnicaTerapeutica,
            true
        );

        return redirect()
            ->route('catalogos.tecnicas.index')
            ->with(
                $tecnicaActualizado->wasChanged('activo') ? 'success' : 'warning',
                $tecnicaActualizado->wasChanged('activo')
                    ? 'Técnica terapéutica reactivada correctamente.'
                    : 'La técnica terapéutica ya se encontraba activa.'
            );
    }
}
