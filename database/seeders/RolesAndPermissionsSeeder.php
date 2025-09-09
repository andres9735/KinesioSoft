<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpia cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /**
         * 📌 1) Crear permisos base
         */
        $permissions = [
            'ver_usuarios',
            'crear_usuarios',
            'editar_usuarios',
            'eliminar_usuarios',

            'ver_roles',
            'crear_roles',
            'editar_roles',
            'eliminar_roles',

            'ver_turnos',
            'crear_turnos',
            'editar_turnos',
            'cancelar_turnos',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(
                ['name' => $p, 'guard_name' => 'web']
            );
        }

        /**
         * 📌 2) Crear roles y asignar permisos
         */
        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $kinesiologa = Role::firstOrCreate(['name' => 'Kinesiologa', 'guard_name' => 'web']);
        $paciente = Role::firstOrCreate(['name' => 'Paciente', 'guard_name' => 'web']);
        $recepcionista = Role::firstOrCreate(['name' => 'Recepcionista', 'guard_name' => 'web']);

        // Asignar todos los permisos al Administrador
        $admin->syncPermissions(Permission::all());

        // Ejemplo: permisos específicos para cada rol
        $kinesiologa->syncPermissions(['ver_turnos', 'crear_turnos', 'editar_turnos']);
        $paciente->syncPermissions(['ver_turnos', 'crear_turnos', 'cancelar_turnos']);
        $recepcionista->syncPermissions(['ver_turnos', 'crear_turnos', 'editar_turnos', 'cancelar_turnos']);

        /**
         * 📌 3) Crear usuario admin por defecto
         */
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@kinesiosoft.test'],
            [
                'name' => 'Admin',
                'password' => bcrypt('admin123'), // Podés cambiar la contraseña
            ]
        );

        if (! $superAdmin->hasRole('Administrador')) {
            $superAdmin->assignRole('Administrador');
        }
    }
}
