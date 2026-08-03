<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurisdiccion extends Model
{
    use HasFactory;

    protected $table = 'jurisdicciones';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
    ];

    public function colegiosEmisores()
    {
        return $this->hasMany(ColegioEmisor::class);
    }

    public function matriculasProfesionales()
    {
        return $this->hasMany(MatriculaProfesionalKinesiologia::class);
    }
}
