<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($datos): void {
            $superadmin = User::query()
                ->where('email', $datos['email'])
                ->first();

            if ($superadmin !== null) {
                $roles = $superadmin->getRoleNames();

                if (
                    ! $roles->contains(RoleEnum::SUPERADMIN->value) ||
                    $roles->count() !== 1
                ) {
                    throw new RuntimeException(
                        'El email configurado para el superadministrador inicial ' .
                            'ya pertenece a un usuario que no posee exclusivamente ' .
                            'el rol superadmin. Revise ' .
                            'KINESIOSOFT_SUPERADMIN_EMAIL antes de ejecutar los seeders.'
                    );
                }

                return;
            }

            $superadmin = User::create([
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
                'activo' => true,
                'email_verified_at' => now(),
            ]);

            $superadmin->syncRoles([
                RoleEnum::SUPERADMIN->value,
            ]);
        });
    }
}
