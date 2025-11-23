<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\EntradaHc;
use App\Models\AntecedentePersonal;
use App\Models\AntecedenteFamiliar;
use App\Models\Alergia;
use App\Models\Cirugia;

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

    /** Listado directo de antecedentes a través de entrada_hc */
    public function antecedentesPersonales(): HasManyThrough
    {
        return $this->hasManyThrough(
            AntecedentePersonal::class, // Related
            EntradaHc::class,           // Through
            'paciente_id',              // FK en entrada_hc -> pacientes.paciente_id
            'entrada_hc_id',            // FK en antecedente_personal -> entrada_hc.entrada_hc_id
            'paciente_id',              // PK local en pacientes
            'entrada_hc_id'             // PK local en entrada_hc
        );
    }

    public function antecedentesFamiliares(): HasManyThrough
    {
        return $this->hasManyThrough(
            AntecedenteFamiliar::class, // related
            EntradaHc::class,           // through
            'paciente_id',              // FK en entrada_hc -> pacientes.paciente_id
            'entrada_hc_id',            // FK en antecedente_familiar -> entrada_hc.entrada_hc_id
            'paciente_id',              // PK local en pacientes
            'entrada_hc_id'             // PK local en entrada_hc
        );
    }

    public function alergias(): HasManyThrough
    {
        return $this->hasManyThrough(
            Alergia::class,   // related
            EntradaHc::class, // through
            'paciente_id',    // FK en entrada_hc -> pacientes.paciente_id
            'entrada_hc_id',  // FK en alergia -> entrada_hc.entrada_hc_id
            'paciente_id',    // PK local en pacientes
            'entrada_hc_id'   // PK local en entrada_hc
        );
    }

    public function cirugias(): HasManyThrough
    {
        return $this->hasManyThrough(
            Cirugia::class,     // related
            EntradaHc::class,   // through
            'paciente_id',      // FK en entrada_hc -> pacientes.paciente_id
            'entrada_hc_id',    // FK en cirugia -> entrada_hc.entrada_hc_id
            'paciente_id',      // PK local en pacientes
            'entrada_hc_id'     // PK local en entrada_hc
        );
    }


    // public function turnos(): HasMany
    // {
    //     return $this->hasMany(\App\Models\Turno::class, 'paciente_perfil_id', 'paciente_id');
    // }
}
