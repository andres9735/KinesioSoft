<?php

namespace App\Models;

use App\Models\Concerns\EsRegistroClinicoInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class ConfirmacionRevisionDeclaracion extends Model
{
    use EsRegistroClinicoInmutable;

    public const ACCION = 'CONFIRMAR_REVISION_E_INCORPORAR';

    protected $table = 'confirmaciones_revision_declaracion';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['clave_confirmacion'];

    protected function casts(): array
    {
        return ['confirmada_at' => 'immutable_datetime', 'linaje_resultado' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $registro): void {
            if ($registro->accion !== self::ACCION) {
                throw new LogicException('La confirmación debe identificar el acto profesional explícito.');
            }
        });
    }

    public function declaracion(): BelongsTo
    {
        return $this->belongsTo(DeclaracionSaludPaciente::class, 'declaracion_salud_id');
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function versionBase(): BelongsTo
    {
        return $this->belongsTo(VersionDatosClinicosBase::class, 'version_base_id');
    }

    public function autora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(EspecialidadKinesiologia::class, 'especialidad_id');
    }

    public function turnoOrigen(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_origen_id');
    }

    public function dictamenes(): HasMany
    {
        return $this->hasMany(RevisionDeclaracionSalud::class, 'confirmacion_revision_id');
    }

    public function incorporacion(): HasOne
    {
        return $this->hasOne(IncorporacionDeclaracionSalud::class, 'confirmacion_revision_id');
    }
}
