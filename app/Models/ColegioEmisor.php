<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColegioEmisor extends Model
{
    use HasFactory;

    protected $table = 'colegios_emisores';

    protected $fillable = [
        'nombre',
        'sigla',
        'jurisdiccion_id',
        'activo',
    ];

    public function jurisdiccion()
    {
        return $this->belongsTo(Jurisdiccion::class);
    }

    public function matriculasProfesionales()
    {
        return $this->hasMany(MatriculaProfesionalKinesiologia::class);
    }
}
