<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilPaciente\ValidarPaso1PerfilPacienteRequest;
use App\Http\Requests\PerfilPaciente\ValidarPaso2PerfilPacienteRequest;
use App\Actions\Pacientes\GuardarPerfilPacienteAction;
use App\Http\Requests\UpdatePerfilPacienteRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerfilPacienteController extends Controller
{
    public function __construct(
        private readonly GuardarPerfilPacienteAction $guardarPerfilPacienteAction
    ) {}

    /**
     * Muestra el formulario de completado de perfil.
     */
    public function edit(): View
    {
        return view('perfil.completar-paciente');
    }

    public function validarPaso1(
        ValidarPaso1PerfilPacienteRequest $request
    ): Response {
        $request->validated();

        return response()->noContent();
    }

    public function validarPaso2(
        ValidarPaso2PerfilPacienteRequest $request
    ): Response {
        $request->validated();

        return response()->noContent();
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

        $this->guardarPerfilPacienteAction->ejecutar(
            $user,
            $data
        );

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                'Perfil completado correctamente.'
            );
    }
}
