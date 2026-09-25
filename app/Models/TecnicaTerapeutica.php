<?php

namespace App\Models;

use App\Enums\SistemaOrigenTecnicaTerapeuticaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TecnicaTerapeutica extends Model
{
    use HasFactory;

    protected $table = 'tecnicas_terapeuticas';

    protected $fillable = [
        'sistema_origen',
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'sistema_origen' => SistemaOrigenTecnicaTerapeuticaEnum::class,
            'activo' => 'boolean',
        ];
    }
}
