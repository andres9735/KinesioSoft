<?php

namespace App\Models;

use App\Enums\TipoCategoriaClinicaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaClinica extends Model
{
    use HasFactory;

    protected $table = 'categorias_clinicas';

    protected $fillable = [
        'categoria_padre_id',
        'codigo',
        'nombre',
        'tipo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCategoriaClinicaEnum::class,
            'activo' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(CategoriaClinica::class, 'categoria_padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(CategoriaClinica::class, 'categoria_padre_id');
    }

    public function ejercicios(): BelongsToMany
    {
        return $this->belongsToMany(
            EjercicioDomiciliario::class,
            'ejercicio_categoria',
            'categoria_id',
            'ejercicio_id'
        );
    }

    public function tiposEquipo(): HasMany
    {
        return $this->hasMany(TipoEquipoTerapeutico::class, 'categoria_clinica_id');
    }
}
