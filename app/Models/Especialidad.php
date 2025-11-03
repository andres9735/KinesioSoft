<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    protected $table = 'especialidades';

    protected $fillable = [
        'nombre',
        'slug',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    // usuarios (kinesiólogas) que la tienen
    public function users()
    {
        return $this->belongsToMany(User::class, 'especialidad_user')
            ->withPivot('is_principal')
            ->withTimestamps();
    }
}
