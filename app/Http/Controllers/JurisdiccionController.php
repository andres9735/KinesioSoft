<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarJurisdiccionAction;
use App\Actions\Catalogos\CambiarEstadoJurisdiccionAction;
use App\Actions\Catalogos\CrearJurisdiccionAction;
use App\Http\Requests\StoreJurisdiccionRequest;
use App\Http\Requests\UpdateJurisdiccionRequest;
use App\Models\Jurisdiccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JurisdiccionController extends Controller
{
    public function __construct(
        private readonly CrearJurisdiccionAction $crearJurisdiccionAction,
        private readonly ActualizarJurisdiccionAction $actualizarJurisdiccionAction,
        private readonly CambiarEstadoJurisdiccionAction $cambiarEstadoJurisdiccionAction
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Jurisdiccion::class);

        $jurisdicciones = Jurisdiccion::query()
            ->orderBy('nombre')
            ->paginate(10);

        return view(
            'catalogos.jurisdicciones.index',
            compact('jurisdicciones')
        );
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

    public function toggleActivo(
        Jurisdiccion $jurisdiccion
    ): RedirectResponse {
        Gate::authorize('toggleActivo', $jurisdiccion);

        $jurisdiccionActualizada =
            $this->cambiarEstadoJurisdiccionAction->ejecutar(
                $jurisdiccion
            );

        $mensaje = $jurisdiccionActualizada->activo
            ? 'Jurisdicción activada correctamente.'
            : 'Jurisdicción inactivada correctamente.';

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with('success', $mensaje);
    }
}
