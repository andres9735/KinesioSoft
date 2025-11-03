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

        // 🔹 Catálogo de ejercicios
        $this->call([
            CatalogoEjerciciosSeeder::class,
        ]);

        // 🔹 Catálogo de técnicas terapéuticas
        $this->call([
            CatalogoTecnicasSeeder::class,
        ]);

        // 🔹 Catálogo de equipos terapéuticos
        $this->call([
            CatalogoEquiposSeeder::class,
        ]);

        // 🔹 Catálogos complementarios (movimientos y métodos ROM)
        $this->call([
            MovimientoSeeder::class,
            MetodoRomSeeder::class,
        ]);

        // 🔹 Catálogo de especialidades kinesiologicas
        $this->call([
            EspecialidadesSeeder::class,
        ]);
    }
}
