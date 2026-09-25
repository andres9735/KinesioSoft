<?php

namespace App\Models;

use App\Enums\SistemaOrigenPruebaFuncionalEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PruebaFuncional extends Model
{
    use HasFactory;

    protected $table = 'pruebas_funcionales';

    protected $fillable = [
        'sistema_origen',
        'codigo',
        'nombre',
        'descripcion',
        'unidad_medida',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'sistema_origen' => SistemaOrigenPruebaFuncionalEnum::class,
            'activo' => 'boolean',
        ];
    }
}
