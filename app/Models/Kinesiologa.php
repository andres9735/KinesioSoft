<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kinesiologa extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'titulo_profesional_id',
        'consultorio_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tituloProfesional()
    {
        return $this->belongsTo(TituloProfesional::class);
    }

    public function matriculasProfesionales()
    {
        return $this->hasMany(MatriculaProfesionalKinesiologia::class);
    }

    public function configuracionesAgenda(): HasMany
    {
        return $this->hasMany(ConfiguracionAgenda::class);
    }

    public function bloqueosAgenda(): HasMany
    {
        return $this->hasMany(BloqueoAgenda::class);
    }

    public function especialidades()
    {
        return $this->belongsToMany(
            EspecialidadKinesiologia::class,
            'kinesiologa_especialidad',
            'kinesiologa_id',
            'especialidad_id'
        )->withTimestamps();
    }
}
