<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntecedentePersonalTipo extends Model
{
    protected $table = 'antecedente_personal_tipo';
    protected $primaryKey = 'antecedente_personal_tipo_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
        'orden',
    ];

    public function antecedentes()
    {
        return $this->hasMany(AntecedentePersonal::class, 'tipo_id', 'antecedente_personal_tipo_id');
    }
}
