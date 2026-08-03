<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EspecialidadKinesiologia extends Model
{
    use HasFactory;

    protected $table = 'especialidades_kinesiologia';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
    ];

    public function kinesiologas()
    {
        return $this->belongsToMany(
            Kinesiologa::class,
            'kinesiologa_especialidad',
            'especialidad_id',
            'kinesiologa_id'
        )->withTimestamps();
    }
}
