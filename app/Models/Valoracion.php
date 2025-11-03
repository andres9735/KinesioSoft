<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Valoracion extends Model
{
    protected $table = 'valoraciones';

    protected $fillable = [
        'profesional_id',
        'paciente_id',
        'turno_id',
        'puntuacion',
        'comentario',
    ];

    public function profesional()
    {
        return $this->belongsTo(User::class, 'profesional_id');
    }

    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class, 'turno_id', 'id_turno');
    }
}
