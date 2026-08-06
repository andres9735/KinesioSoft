<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEspecialidadKinesiologiaRequest;
use App\Http\Requests\UpdateEspecialidadKinesiologiaRequest;
use App\Models\EspecialidadKinesiologia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EspecialidadKinesiologiaController extends Controller
{
    public function index(): View
    {
        $especialidades = EspecialidadKinesiologia::query()
            ->orderBy('nombre')
            ->paginate(10);

        return view(
            'catalogos.especialidades.index',
            compact('especialidades')
        );
    }

    public function create(): View
    {
        return view('catalogos.especialidades.create');
    }

    public function store(
        StoreEspecialidadKinesiologiaRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        $nombre = preg_replace(
            '/\s+/',
            ' ',
            trim($data['nombre'])
        );

        EspecialidadKinesiologia::create([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => true,
        ]);

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with(
                'success',
                'Especialidad de kinesiología creada correctamente.'
            );
    }

    public function edit(
        EspecialidadKinesiologia $especialidad
    ): View {
        return view(
            'catalogos.especialidades.edit',
            compact('especialidad')
        );
    }

    public function update(
        UpdateEspecialidadKinesiologiaRequest $request,
        EspecialidadKinesiologia $especialidad
    ): RedirectResponse {
        $data = $request->validated();

        $nombre = preg_replace(
            '/\s+/',
            ' ',
            trim($data['nombre'])
        );

        $especialidad->update([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
        ]);

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with(
                'success',
                'Especialidad de kinesiología actualizada correctamente.'
            );
    }

    public function toggleActivo(
        EspecialidadKinesiologia $especialidad
    ): RedirectResponse {
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
}
