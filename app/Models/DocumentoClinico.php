<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class DocumentoClinico extends Model
{
    use HasFactory;

    public const ORIGEN_SOLICITUD_TURNO = 'SOLICITUD_TURNO';

    public const ESTADO_VALIDACION_PENDIENTE = 'PENDIENTE';

    public const TIPO_SIN_CLASIFICAR = 'SIN_CLASIFICAR';

    protected $table = 'documentos_clinicos';

    protected $fillable = [
        'paciente_id',
        'cargado_por_user_id',
        'origen',
        'estado_validacion',
        'tipo_documento_clinico',
        'nombre_original',
        'descripcion',
        'ruta_storage',
        'mime_type',
        'tamanio_bytes',
    ];

    protected function casts(): array
    {
        return [
            'tamanio_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $documento): void {
            if ($documento->isDirty([
                'paciente_id',
                'cargado_por_user_id',
                'origen',
                'nombre_original',
                'descripcion',
                'ruta_storage',
                'mime_type',
                'tamanio_bytes',
            ])) {
                throw new LogicException('El original del documento clínico no puede modificarse.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Los documentos clínicos originales no pueden eliminarse.');
        });
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_user_id');
    }

    public function origenTurno(): HasOne
    {
        return $this->hasOne(DocumentoClinicoOrigenTurno::class, 'documento_clinico_id');
    }
}
