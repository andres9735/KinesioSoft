<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Localidad extends Model
{
    use HasFactory;

    protected $table = 'localidades';

    protected $fillable = [
        'georef_id',
        'provincia_id',
        'nombre',
        'departamento_nombre',
    ];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function calles(): HasMany
    {
        return $this->hasMany(Calle::class);
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }
}
