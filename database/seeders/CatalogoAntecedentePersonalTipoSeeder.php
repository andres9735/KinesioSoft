<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AntecedentePersonalTipo;
use Illuminate\Support\Str;

class CatalogoAntecedentePersonalTipoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = ['Patológico','Quirúrgico','Tóxico','Fármacológico','Traumático','Psicosocial','Obstétrico','Otro'];

        foreach ($tipos as $i => $nombre) {
            AntecedentePersonalTipo::firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['nombre' => $nombre, 'orden' => $i+1, 'activo' => true]
            );
        }
    }
}

