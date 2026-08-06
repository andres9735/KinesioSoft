<?php

namespace App\Http\Controllers;

use App\Models\TituloProfesional;
use App\Http\Requests\StoreTituloProfesionalRequest;
use Illuminate\Http\Request;
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

    public function update(Request $request, TituloProfesional $titulo): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $nombre = trim($request->nombre);
        $slug = Str::slug($nombre);

        if (TituloProfesional::where('nombre', $nombre)->where('id', '!=', $titulo->id)->exists()) {
            return back()->withErrors([
                'nombre' => 'Ya existe un título profesional con ese nombre.',
            ])->withInput();
        }

        if (TituloProfesional::where('slug', $slug)->where('id', '!=', $titulo->id)->exists()) {
            return back()->withErrors([
                'nombre' => 'El nombre ingresado genera un slug duplicado.',
            ])->withInput();
        }

        $titulo->update([
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('catalogos.titulos.index')
            ->with('success', 'Título profesional actualizado correctamente.');
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
