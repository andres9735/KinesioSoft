<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentoClinicoOrigenTurno extends Model
{
    protected $table = 'documentos_clinicos_origen_turno';

    protected $primaryKey = 'documento_clinico_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'documento_clinico_id',
        'turno_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('El origen-turno del documento clínico no puede modificarse.');
        });

        static::deleting(function (): never {
            throw new LogicException('El origen-turno del documento clínico no puede eliminarse.');
        });
    }

    public function documentoClinico(): BelongsTo
    {
        return $this->belongsTo(DocumentoClinico::class, 'documento_clinico_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }
}
