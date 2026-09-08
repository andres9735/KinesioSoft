<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paciente extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'direccion',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function historiaClinica(): HasOne
    {
        return $this->hasOne(HistoriaClinica::class);
    }

    public function declaracionesSalud(): HasMany
    {
        return $this->hasMany(DeclaracionSaludPaciente::class);
    }
}
