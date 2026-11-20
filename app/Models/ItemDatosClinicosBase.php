<?php

namespace App\Models;

use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;

class ItemDatosClinicosBase extends Model
{
    use EsRegistroClinicoInmutable;

    protected $table = 'items_datos_clinicos_base';

    public $timestamps = false;

    protected $guarded = ['id'];
}
