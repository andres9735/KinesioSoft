<?php

namespace App\Http\Controllers;

use App\Actions\Pacientes\CompletarPerfilClinicoInicialAction;
use App\Http\Requests\PerfilPaciente\CompletarPerfilPacienteRequest;
use App\Http\Requests\PerfilPaciente\ValidarPaso1PerfilPacienteRequest;
use App\Http\Requests\PerfilPaciente\ValidarPaso2PerfilPacienteRequest;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class PerfilPacienteController extends Controller
{
    public function __construct(
        private readonly CompletarPerfilClinicoInicialAction $completarPerfilClinicoInicialAction
    ) {}

    /**
     * Muestra el formulario de completado del perfil clínico inicial
     * o la vista de consulta del perfil ya completado.
     */
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->perfilCompleto()) {
            $user->loadMissing('paciente.localidad');

            return view(
                'perfil.completar-paciente',
                [
                    'user' => $user,
                    'paciente' => $user->paciente,
                ]
            );
        }

        $paciente = $user->paciente;

        $declaracion = $paciente
            ? $paciente->declaracionesSalud()
                ->with('items')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first()
            : null;

        return view(
            'perfil.mostrar-paciente',
            [
                'user' => $user,
                'paciente' => $paciente,
                'declaracion' => $declaracion,
            ]
        );
    }

    /**
     * Valida el paso 1 del wizard sin persistir datos.
     */
    public function validarPaso1(
        ValidarPaso1PerfilPacienteRequest $request
    ): Response {
        $request->validated();

        return response()->noContent();
    }

    /**
     * Valida el paso 2 del wizard sin persistir datos.
     */
    public function validarPaso2(
        ValidarPaso2PerfilPacienteRequest $request
    ): Response {
        $request->validated();

        return response()->noContent();
    }

    /**
     * Completa definitivamente el perfil clínico inicial.
     */
    public function update(
        CompletarPerfilPacienteRequest $request
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        try {
            $this->completarPerfilClinicoInicialAction->ejecutar(
                $user,
                $data
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with(
                    'warning',
                    $exception->getMessage()
                );
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'No se pudo completar el perfil clínico inicial por un '
                        .'inconveniente técnico. Los cambios no fueron '
                        .'aplicados. Intente nuevamente.'
                );
        }

        $mensajeExito =
            'Perfil clínico inicial completado correctamente.';

        if (! $user->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with(
                    'success',
                    $mensajeExito
                        .' Verifique su correo electrónico para continuar.'
                );
        }

        return redirect()
            ->route('dashboard')
            ->with(
                'success',
                $mensajeExito
            );
    }
}
