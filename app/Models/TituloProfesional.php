<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TituloProfesional extends Model
{
    use HasFactory;

    protected $table = 'titulos_profesionales';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
    ];

    public function kinesiologas()
    {
        return $this->hasMany(Kinesiologa::class);
    }
}
