<?php

namespace App\Http\Controllers;

use App\Models\TituloProfesional;
use App\Http\Requests\StoreTituloProfesionalRequest;
use App\Http\Requests\UpdateTituloProfesionalRequest;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class TituloProfesionalController extends Controller
{
    public function index(): View
    {
        $titulos = TituloProfesional::orderBy('nombre')->paginate(10);

        return view('catalogos.titulos.index', compact('titulos'));
    }

    public function create(): View
    {
        return view('catalogos.titulos.create');
    }

    public function store(
        StoreTituloProfesionalRequest $request
    ): RedirectResponse {
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
        return view('catalogos.titulos.edit', compact('titulo'));
    }

    public function update(
        UpdateTituloProfesionalRequest $request,
        TituloProfesional $titulo
    ): RedirectResponse {
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

    public function toggleActivo(TituloProfesional $titulo): RedirectResponse
    {
        $titulo->update([
            'activo' => ! $titulo->activo,
        ]);

        return redirect()
            ->route('catalogos.titulos.index')
            ->with('success', 'Estado actualizado correctamente.');
    }
}
