<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarJurisdiccionAction;
use App\Actions\Catalogos\CambiarEstadoJurisdiccionAction;
use App\Actions\Catalogos\CrearJurisdiccionAction;
use App\Http\Requests\StoreJurisdiccionRequest;
use App\Http\Requests\UpdateJurisdiccionRequest;
use App\Models\Jurisdiccion;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JurisdiccionController extends Controller
{
    public function __construct(
        private readonly CrearJurisdiccionAction $crearJurisdiccionAction,
        private readonly ActualizarJurisdiccionAction $actualizarJurisdiccionAction,
        private readonly CambiarEstadoJurisdiccionAction $cambiarEstadoJurisdiccionAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Jurisdiccion::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $jurisdicciones = Jurisdiccion::query()
            ->when($q !== '', fn ($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view(
            'catalogos.jurisdicciones.index',
            compact('jurisdicciones', 'q', 'estado')
        );
    }

    public function show(Jurisdiccion $jurisdiccion): View
    {
        Gate::authorize('view', $jurisdiccion);

        return view('catalogos.jurisdicciones.show', compact('jurisdiccion'));
    }

    public function create(): View
    {
        Gate::authorize('create', Jurisdiccion::class);

        return view('catalogos.jurisdicciones.create');
    }

    public function store(
        StoreJurisdiccionRequest $request
    ): RedirectResponse {
        Gate::authorize('create', Jurisdiccion::class);

        $data = $request->validated();

        $this->crearJurisdiccionAction->ejecutar($data);

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with(
                'success',
                'Jurisdicción creada correctamente.'
            );
    }

    public function edit(Jurisdiccion $jurisdiccion): View
    {
        Gate::authorize('update', $jurisdiccion);

        return view(
            'catalogos.jurisdicciones.edit',
            compact('jurisdiccion')
        );
    }

    public function update(
        UpdateJurisdiccionRequest $request,
        Jurisdiccion $jurisdiccion
    ): RedirectResponse {
        Gate::authorize('update', $jurisdiccion);

        $data = $request->validated();

        $this->actualizarJurisdiccionAction->ejecutar(
            $jurisdiccion,
            $data
        );

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with(
                'success',
                'Jurisdicción actualizada correctamente.'
            );
    }

    public function inactivar(
        Jurisdiccion $jurisdiccion
    ): RedirectResponse {
        Gate::authorize('inactivar', $jurisdiccion);

        try {
            $jurisdiccionActualizada = $this->cambiarEstadoJurisdiccionAction->ejecutar(
                $jurisdiccion,
                false
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('catalogos.jurisdicciones.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with(
                $jurisdiccionActualizada->wasChanged('activo') ? 'success' : 'warning',
                $jurisdiccionActualizada->wasChanged('activo')
                    ? 'Jurisdicción inactivada correctamente.'
                    : 'La jurisdicción ya se encontraba inactiva.'
            );
    }

    public function reactivar(
        Jurisdiccion $jurisdiccion
    ): RedirectResponse {
        Gate::authorize('reactivar', $jurisdiccion);

        $jurisdiccionActualizada =
            $this->cambiarEstadoJurisdiccionAction->ejecutar(
                $jurisdiccion,
                true
            );

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with(
                $jurisdiccionActualizada->wasChanged('activo') ? 'success' : 'warning',
                $jurisdiccionActualizada->wasChanged('activo')
                    ? 'Jurisdicción reactivada correctamente.'
                    : 'La jurisdicción ya se encontraba activa.'
            );
    }
}
