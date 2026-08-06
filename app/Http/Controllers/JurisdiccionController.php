<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJurisdiccionRequest;
use App\Http\Requests\UpdateJurisdiccionRequest;
use App\Models\Jurisdiccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JurisdiccionController extends Controller
{
    public function index(): View
    {
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
        return view('catalogos.jurisdicciones.create');
    }

    public function store(
        StoreJurisdiccionRequest $request
    ): RedirectResponse {
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
        return view(
            'catalogos.jurisdicciones.edit',
            compact('jurisdiccion')
        );
    }

    public function update(
        UpdateJurisdiccionRequest $request,
        Jurisdiccion $jurisdiccion
    ): RedirectResponse {
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
