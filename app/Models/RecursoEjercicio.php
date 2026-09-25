<?php

namespace App\Models;

use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecursoEjercicio extends Model
{
    use HasFactory;

    protected $table = 'recursos_ejercicio';

    protected $fillable = [
        'ejercicio_id',
        'tipo',
        'origen',
        'ruta_storage',
        'url_externa',
        'titulo',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRecursoEjercicioEnum::class,
            'origen' => OrigenRecursoEjercicioEnum::class,
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(EjercicioDomiciliario::class, 'ejercicio_id');
    }
}
