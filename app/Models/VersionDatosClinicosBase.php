<?php

namespace App\Models;

use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersionDatosClinicosBase extends Model
{
    use EsRegistroClinicoInmutable;

    protected $table = 'versiones_datos_clinicos_base';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['clave_apertura', 'huella_solicitud'];

    protected function casts(): array
    {
        return ['relevamientos' => 'array', 'created_at' => 'immutable_datetime', 'peso_kg' => 'decimal:2'];
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemDatosClinicosBase::class)->orderBy('id');
    }

    public function integrantes(): HasMany
    {
        return $this->hasMany(IntegranteNucleoFamiliarHc::class)->orderBy('id');
    }
}
