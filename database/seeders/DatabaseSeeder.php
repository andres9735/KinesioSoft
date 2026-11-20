<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermisoHistoriaClinicaSeeder::class,
            SuperadminUserSeeder::class,
            JurisdiccionSeeder::class,
            ColegioEmisorSeeder::class,
            TituloProfesionalSeeder::class,
            EspecialidadKinesiologiaSeeder::class,
        ]);
    }
}
