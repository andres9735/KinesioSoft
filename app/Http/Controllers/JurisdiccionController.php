<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJurisdiccionRequest;
use App\Http\Requests\UpdateJurisdiccionRequest;
use App\Models\Jurisdiccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JurisdiccionController extends Controller
{
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

        $nombre = $data['nombre'];
        $slug = Str::slug($nombre);

        Jurisdiccion::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'activo' => true,
        ]);

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

        $nombre = $data['nombre'];
        $slug = Str::slug($nombre);

        $jurisdiccion->update([
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

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

        $jurisdiccion->update([
            'activo' => ! $jurisdiccion->activo,
        ]);

        $mensaje = $jurisdiccion->activo
            ? 'Jurisdicción activada correctamente.'
            : 'Jurisdicción inactivada correctamente.';

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with('success', $mensaje);
    }
}
