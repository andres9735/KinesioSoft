<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = 'web';
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'Kinesiologa',   'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'Paciente',      'guard_name' => $guard]);
    }
}
