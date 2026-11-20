<?php

namespace App\Models;

use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;

class IntegranteNucleoFamiliarHc extends Model
{
    use EsRegistroClinicoInmutable;

    protected $table = 'integrantes_nucleo_familiar_hc';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contacto_emergencia_confirmado' => 'boolean'];
    }
}
