<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paciente extends Model
{
    protected $table = 'pacientes';
    protected $primaryKey = 'paciente_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'nombre',
        'dni',
        'telefono',
        'direccion',
        'fecha_nacimiento',
        'observaciones',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function entradasHc(): HasMany
    {
        return $this->hasMany(EntradaHc::class, 'paciente_id', 'paciente_id');
    }

    // public function turnos(): HasMany
    // {
    //     return $this->hasMany(\App\Models\Turno::class, 'paciente_perfil_id', 'paciente_id');
    // }
}
