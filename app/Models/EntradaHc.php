<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Alergia;

class EntradaHc extends Model
{
    protected $table = 'entrada_hc';
    protected $primaryKey = 'entrada_hc_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'paciente_id',
        'fecha',
        'fecha_creacion',
        'creado_por',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'fecha_creacion' => 'datetime',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'paciente_id');
    }

    public function antecedentesPersonales(): HasMany
    {
        return $this->hasMany(AntecedentePersonal::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    public function antecedentesFamiliares(): HasMany
    {
        return $this->hasMany(AntecedenteFamiliar::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    public function alergias(): HasMany
    {
        return $this->hasMany(Alergia::class, 'entrada_hc_id', 'entrada_hc_id');
    }
}
