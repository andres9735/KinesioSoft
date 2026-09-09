<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_nombre_snapshot',
        'actor_email_snapshot',
        'evento',
        'modulo',
        'auditable_type',
        'auditable_id',
        'valores_anteriores',
        'valores_nuevos',
        'url',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Los registros de auditoría son inmutables y no pueden modificarse.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Los registros de auditoría son inmutables y no pueden eliminarse.'
            );
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
