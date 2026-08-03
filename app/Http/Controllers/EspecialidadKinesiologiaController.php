<?php

namespace App\Http\Controllers;

use App\Models\EspecialidadKinesiologia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EspecialidadKinesiologiaController extends Controller
{
    public function index(): View
    {
        $especialidades = EspecialidadKinesiologia::query()
            ->orderBy('nombre')
            ->paginate(10);

        return view('catalogos.especialidades.index', compact('especialidades'));
    }

    public function create(): View
    {
        return view('catalogos.especialidades.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        EspecialidadKinesiologia::create([
            'nombre' => $data['nombre'],
            'slug' => $data['slug'],
            'activo' => true,
        ]);

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with('success', 'Especialidad de kinesiología creada correctamente.');
    }

    public function edit(EspecialidadKinesiologia $especialidad): View
    {
        return view('catalogos.especialidades.edit', compact('especialidad'));
    }

    public function update(Request $request, EspecialidadKinesiologia $especialidad): RedirectResponse
    {
        $data = $this->validateData($request, $especialidad);

        $especialidad->update([
            'nombre' => $data['nombre'],
            'slug' => $data['slug'],
        ]);

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with('success', 'Especialidad de kinesiología actualizada correctamente.');
    }

    public function toggleActivo(EspecialidadKinesiologia $especialidad): RedirectResponse
    {
        $especialidad->update([
            'activo' => ! $especialidad->activo,
        ]);

        $mensaje = $especialidad->activo
            ? 'Especialidad de kinesiología activada correctamente.'
            : 'Especialidad de kinesiología inactivada correctamente.';

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with('success', $mensaje);
    }

    private function validateData(Request $request, ?EspecialidadKinesiologia $especialidad = null): array
    {
        $especialidadId = $especialidad?->id;

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\s]+$/u',
                Rule::unique('especialidades_kinesiologia', 'nombre')->ignore($especialidadId),
            ],
        ], [
            'nombre.required' => 'Debe ingresar el nombre de la especialidad.',
            'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'nombre.unique' => 'Ya existe una especialidad de kinesiología con ese nombre.',
        ]);

        $data['nombre'] = preg_replace('/\s+/', ' ', trim($data['nombre']));
        $data['slug'] = Str::slug($data['nombre']);

        $slugExists = EspecialidadKinesiologia::query()
            ->where('slug', $data['slug'])
            ->when($especialidadId, fn($query) => $query->where('id', '!=', $especialidadId))
            ->exists();

        if ($slugExists) {
            return back()
                ->withErrors([
                    'nombre' => 'El nombre ingresado genera un slug que ya existe. Intente con una variante distinta.',
                ])
                ->withInput()
                ->throwResponse();
        }

        return $data;
    }
}
