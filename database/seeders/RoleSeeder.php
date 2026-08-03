<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value]);
        Role::firstOrCreate(['name' => RoleEnum::ADMINISTRADOR->value]);
        Role::firstOrCreate(['name' => RoleEnum::KINESIOLOGO->value]);
        Role::firstOrCreate(['name' => RoleEnum::PACIENTE->value]);
    }
}
