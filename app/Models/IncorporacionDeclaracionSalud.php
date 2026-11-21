<?php

namespace App\Models;

use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncorporacionDeclaracionSalud extends Model
{
    use EsRegistroClinicoInmutable;

    protected $table = 'incorporaciones_declaracion_salud';

    protected $primaryKey = 'version_datos_clinicos_base_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionDatosClinicosBase::class, 'version_datos_clinicos_base_id');
    }

    public function confirmacion(): BelongsTo
    {
        return $this->belongsTo(ConfirmacionRevisionDeclaracion::class, 'confirmacion_revision_id');
    }

    public function declaracion(): BelongsTo
    {
        return $this->belongsTo(DeclaracionSaludPaciente::class, 'declaracion_salud_id');
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }
}
