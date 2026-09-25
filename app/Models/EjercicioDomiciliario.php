<?php

namespace App\Models;

use App\Enums\DificultadEjercicioEnum;
use App\Enums\FrecuenciaPeriodoEjercicioEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EjercicioDomiciliario extends Model
{
    use HasFactory;

    protected $table = 'ejercicios_domiciliarios';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'series_recomendadas',
        'repeticiones_recomendadas',
        'duracion_segundos_recomendada',
        'frecuencia_cantidad_base',
        'frecuencia_periodo_base',
        'descanso_segundos_base',
        'carga_resistencia_sugerida',
        'dificultad_base',
        'contraindicaciones',
        'notas_clinicas',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'series_recomendadas' => 'integer',
            'repeticiones_recomendadas' => 'integer',
            'duracion_segundos_recomendada' => 'integer',
            'frecuencia_cantidad_base' => 'integer',
            'frecuencia_periodo_base' => FrecuenciaPeriodoEjercicioEnum::class,
            'descanso_segundos_base' => 'integer',
            'dificultad_base' => DificultadEjercicioEnum::class,
            'activo' => 'boolean',
        ];
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoriaClinica::class,
            'ejercicio_categoria',
            'ejercicio_id',
            'categoria_id'
        );
    }

    public function recursos(): HasMany
    {
        return $this->hasMany(RecursoEjercicio::class, 'ejercicio_id');
    }
}
