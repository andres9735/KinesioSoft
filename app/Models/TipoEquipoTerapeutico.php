<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoEquipoTerapeutico extends Model
{
    use HasFactory;

    protected $table = 'tipos_equipo_terapeutico';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'categoria_clinica_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function categoriaClinica(): BelongsTo
    {
        return $this->belongsTo(CategoriaClinica::class, 'categoria_clinica_id');
    }
}
