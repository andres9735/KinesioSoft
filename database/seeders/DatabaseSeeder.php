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
        // 🔹 Roles, permisos y usuarios base
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
        ]);

        // 🔹 Catálogos clínicos (diagnósticos, patologías, zonas, padecimientos)
        $this->call([
            CatalogosClinicosSeeder::class,
        ]);

        // 🔹 Catálogo de ejercicios (nuevo módulo)
        $this->call([
            CatalogoEjerciciosSeeder::class,
        ]);

        // 🔹 Catálogo de técnicas terapéuticas (nuevo módulo)
        $this->call([
            CatalogoTecnicasSeeder::class,
        ]);
    }
}
