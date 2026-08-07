<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTituloProfesionalRequest;
use App\Http\Requests\UpdateTituloProfesionalRequest;
use App\Models\TituloProfesional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TituloProfesionalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TituloProfesional::class);

        $titulos = TituloProfesional::orderBy('nombre')->paginate(10);

        return view('catalogos.titulos.index', compact('titulos'));
    }

    public function create(): View
    {
        Gate::authorize('create', TituloProfesional::class);

        return view('catalogos.titulos.create');
    }

    public function store(
        StoreTituloProfesionalRequest $request
    ): RedirectResponse {
        Gate::authorize('create', TituloProfesional::class);

        $data = $request->validated();

        $nombre = $data['nombre'];
        $slug = Str::slug($nombre);

        TituloProfesional::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'activo' => true,
        ]);

        return redirect()
            ->route('catalogos.titulos.index')
            ->with(
                'success',
                'Título profesional creado correctamente.'
            );
    }

    public function edit(TituloProfesional $titulo): View
    {
        Gate::authorize('update', $titulo);

        return view('catalogos.titulos.edit', compact('titulo'));
    }

    public function update(
        UpdateTituloProfesionalRequest $request,
        TituloProfesional $titulo
    ): RedirectResponse {
        Gate::authorize('update', $titulo);

        $data = $request->validated();

        $nombre = $data['nombre'];
        $slug = Str::slug($nombre);

        $titulo->update([
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('catalogos.titulos.index')
            ->with(
                'success',
                'Título profesional actualizado correctamente.'
            );
    }

    public function toggleActivo(
        TituloProfesional $titulo
    ): RedirectResponse {
        Gate::authorize('toggleActivo', $titulo);

        $titulo->update([
            'activo' => ! $titulo->activo,
        ]);

        return redirect()
            ->route('catalogos.titulos.index')
            ->with('success', 'Estado actualizado correctamente.');
    }
}
