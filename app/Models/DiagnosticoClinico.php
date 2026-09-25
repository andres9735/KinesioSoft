<?php

namespace App\Models;

use App\Enums\SistemaOrigenDiagnosticoClinicoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticoClinico extends Model
{
    use HasFactory;

    protected $table = 'diagnosticos_clinicos';

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
            'sistema_origen' => SistemaOrigenDiagnosticoClinicoEnum::class,
            'activo' => 'boolean',
        ];
    }
}
