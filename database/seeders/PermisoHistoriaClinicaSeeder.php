<?php

namespace Database\Seeders;

use App\Policies\TurnoPolicy;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermisoHistoriaClinicaSeeder extends Seeder
{
    public function run(): void
    {
        // Registrar la capacidad no otorga acceso clínico a ningún rol o cuenta.
        Permission::findOrCreate(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, 'web');
        Permission::findOrCreate(TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA, 'web');
        Permission::findOrCreate(TurnoPolicy::PERMISO_REVISAR_DECLARACION, 'web');
    }
}
