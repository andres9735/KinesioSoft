<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperadminUserSeeder extends Seeder
{
    public function run(): void
    {
        $datos = config('kinesiosoft.bootstrap_superadmin');

        if (
            blank($datos['email'] ?? null) ||
            blank($datos['password'] ?? null)
        ) {
            throw new RuntimeException(
                'Debe configurar KINESIOSOFT_SUPERADMIN_EMAIL y ' .
                    'KINESIOSOFT_SUPERADMIN_PASSWORD en el archivo .env.'
            );
        }

        $superadmin = User::firstOrCreate(
            [
                'email' => $datos['email'],
            ],
            [
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'password' => Hash::make($datos['password']),
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $superadmin->hasRole(RoleEnum::SUPERADMIN->value)) {
            $superadmin->assignRole(RoleEnum::SUPERADMIN->value);
        }
    }
}
