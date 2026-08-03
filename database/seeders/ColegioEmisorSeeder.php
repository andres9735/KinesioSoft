<?php

namespace Database\Seeders;

use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use Illuminate\Database\Seeder;

class ColegioEmisorSeeder extends Seeder
{
    public function run(): void
    {
        $jurisdiccionMisiones = Jurisdiccion::where('slug', 'misiones')->first();

        if (! $jurisdiccionMisiones) {
            return;
        }

        ColegioEmisor::firstOrCreate(
            ['sigla' => 'COKIFIMI'],
            [
                'nombre' => 'Colegio de Kinesiólogos y Fisioterapeutas de Misiones',
                'jurisdiccion_id' => $jurisdiccionMisiones->id,
                'activo' => true,
            ]
        );
    }
}
