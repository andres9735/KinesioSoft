<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * La autorización continúa a cargo de los controles
     * existentes en UserController.
     *
     * Será centralizada mediante Policies en el Incremento 10.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas para actualizar un usuario.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usuarioAutenticado = $this->user();
        $user = $this->route('user');

        $userId = $user instanceof User
            ? $user->id
            : null;

        $rolesPermitidos = $usuarioAutenticado instanceof User
            && $usuarioAutenticado->esSuperadmin()
            ? [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]
            : [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
            ];

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'apellido' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($userId),
            ],

            'rol' => [
                'required',
                Rule::in($rolesPermitidos),
            ],
        ];
    }
}
