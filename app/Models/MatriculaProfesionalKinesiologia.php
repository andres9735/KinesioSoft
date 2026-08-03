<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatriculaProfesionalKinesiologia extends Model
{
    use HasFactory;

    protected $table = 'matriculas_profesionales_kinesiologia';

    protected $fillable = [
        'kinesiologa_id',
        'nro_matricula_profesional',
        'jurisdiccion_id',
        'colegio_emisor_id',
        'activa',
    ];

    public function kinesiologa()
    {
        return $this->belongsTo(Kinesiologa::class);
    }

    public function jurisdiccion()
    {
        return $this->belongsTo(Jurisdiccion::class);
    }

    public function colegioEmisor()
    {
        return $this->belongsTo(ColegioEmisor::class);
    }
}
