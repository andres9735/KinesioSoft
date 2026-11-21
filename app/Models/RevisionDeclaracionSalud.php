<?php

namespace App\Models;

use App\Enums\BloqueRevisionDeclaracion;
use App\Enums\DictamenRevisionDeclaracion;
use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RevisionDeclaracionSalud extends Model
{
    use EsRegistroClinicoInmutable;

    protected $table = 'revisiones_declaracion_salud';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['bloque' => BloqueRevisionDeclaracion::class, 'dictamen' => DictamenRevisionDeclaracion::class,
            'datos_originales' => 'array', 'correccion_profesional' => 'array', 'datos_admitidos' => 'array',
            'procedencias' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $registro): void {
            if ($registro->dictamen->requiereJustificacion() && trim($registro->observacion_profesional ?? '') === '') {
                throw new LogicException('Observar o descartar datos clínicos requiere una justificación.');
            }
        });
    }

    public function confirmacion(): BelongsTo
    {
        return $this->belongsTo(ConfirmacionRevisionDeclaracion::class, 'confirmacion_revision_id');
    }

    public function declaracion(): BelongsTo
    {
        return $this->belongsTo(DeclaracionSaludPaciente::class, 'declaracion_salud_id');
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }
}
