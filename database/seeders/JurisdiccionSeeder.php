<?php

namespace Database\Seeders;

use App\Models\Jurisdiccion;
use Illuminate\Database\Seeder;

class JurisdiccionSeeder extends Seeder
{
    public function run(): void
    {
        Jurisdiccion::firstOrCreate(
            ['slug' => 'misiones'],
            [
                'nombre' => 'Misiones',
                'activo' => true,
            ]
        );
    }
}
