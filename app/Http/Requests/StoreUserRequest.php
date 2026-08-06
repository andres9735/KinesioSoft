<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * La autorización continúa a cargo de los middlewares
     * y controles del módulo de usuarios.
     *
     * Será centralizada mediante Policies en el Incremento 10.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas para crear un usuario desde la administración.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usuarioAutenticado = $this->user();

        $rolesPermitidos = $usuarioAutenticado instanceof User
            && $usuarioAutenticado->esSuperadmin()
            ? [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
                RoleEnum::ADMINISTRADOR->value,
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
                'unique:users,email',
            ],

            'password' => [
                'required',
                'confirmed',
                'min:8',
            ],

            'rol' => [
                'required',
                Rule::in($rolesPermitidos),
            ],
        ];
    }
}
