<?php

namespace App\Http\Controllers;

use App\Models\Jurisdiccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JurisdiccionController extends Controller
{
    public function index(): View
    {
        $jurisdicciones = Jurisdiccion::query()
            ->orderBy('nombre')
            ->paginate(10);

        return view('catalogos.jurisdicciones.index', compact('jurisdicciones'));
    }

    public function create(): View
    {
        return view('catalogos.jurisdicciones.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\s]+$/u',
            ],
        ], [
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
        ]);

        $nombre = trim($request->nombre);
        $slug = Str::slug($nombre);

        $existeNombre = Jurisdiccion::where('nombre', $nombre)->exists();
        if ($existeNombre) {
            return back()
                ->withErrors([
                    'nombre' => 'Ya existe una jurisdicción con ese nombre.',
                ])
                ->withInput();
        }

        $existeSlug = Jurisdiccion::where('slug', $slug)->exists();
        if ($existeSlug) {
            return back()
                ->withErrors([
                    'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
                ])
                ->withInput();
        }

        Jurisdiccion::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'activo' => true,
        ]);

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with('success', 'Jurisdicción creada correctamente.');
    }

    public function edit(Jurisdiccion $jurisdiccion): View
    {
        return view('catalogos.jurisdicciones.edit', compact('jurisdiccion'));
    }

    public function update(Request $request, Jurisdiccion $jurisdiccion): RedirectResponse
    {
        $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\s]+$/u',
            ],
        ], [
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
        ]);

        $nombre = trim($request->nombre);
        $slug = Str::slug($nombre);

        $existeNombre = Jurisdiccion::where('nombre', $nombre)
            ->where('id', '!=', $jurisdiccion->id)
            ->exists();

        if ($existeNombre) {
            return back()
                ->withErrors([
                    'nombre' => 'Ya existe una jurisdicción con ese nombre.',
                ])
                ->withInput();
        }

        $existeSlug = Jurisdiccion::where('slug', $slug)
            ->where('id', '!=', $jurisdiccion->id)
            ->exists();

        if ($existeSlug) {
            return back()
                ->withErrors([
                    'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
                ])
                ->withInput();
        }

        $jurisdiccion->update([
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('catalogos.jurisdicciones.index')
            ->with('success', 'Jurisdicción actualizada correctamente.');
    }

    public function toggleActivo(Jurisdiccion $jurisdiccion): RedirectResponse
    {
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
