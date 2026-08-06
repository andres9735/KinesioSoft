<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePerfilPacienteRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerfilPacienteController extends Controller
{
    /**
     * Muestra el formulario de completado de perfil.
     */
    public function edit(): View
    {
        return view('perfil.completar-paciente');
    }

    /**
     * Actualiza los datos faltantes del perfil del paciente.
     */
    public function update(
        UpdatePerfilPacienteRequest $request
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        $user->update([
            'dni' => $data['dni'],
            'fecha_nacimiento' => $data['fecha_nacimiento'],
            'sexo' => $data['sexo'],
            'telefono' => $data['telefono'],
        ]);

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                'Perfil completado correctamente.'
            );
    }
}
