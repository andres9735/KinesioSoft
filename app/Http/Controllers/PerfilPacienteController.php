<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'dni' => ['required', 'string', 'max:20', 'unique:users,dni,' . Auth::id()],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', 'in:masculino,femenino,otro,prefiero_no_decir'],
            'telefono' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'dni' => $request->dni,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo' => $request->sexo,
            'telefono' => $request->telefono,
        ]);

        return redirect()->route('dashboard')->with('success', 'Perfil completado correctamente.');
    }
}
