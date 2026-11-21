<?php

namespace App\Models;

use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Validator;

class VersionDatosClinicosBase extends Model
{
    use EsRegistroClinicoInmutable;

    protected $table = 'versiones_datos_clinicos_base';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['clave_apertura', 'huella_solicitud'];

    protected function casts(): array
    {
        return ['relevamientos' => 'array', 'created_at' => 'immutable_datetime', 'peso_kg' => 'decimal:2', 'altura_cm' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            Validator::make(['altura_cm' => $version->getAttributes()['altura_cm'] ?? null, 'peso_kg' => $version->getAttributes()['peso_kg'] ?? null], [
                'altura_cm' => ['nullable', 'numeric', 'decimal:0,2', 'between:30,250'],
                'peso_kg' => ['nullable', 'numeric', 'decimal:0,2', 'between:1,500'],
            ])->validate();
        });
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

    public function incorporacion(): HasOne
    {
        return $this->hasOne(IncorporacionDeclaracionSalud::class, 'version_datos_clinicos_base_id');
    }
}
