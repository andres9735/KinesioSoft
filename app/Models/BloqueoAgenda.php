<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueoAgenda extends Model
{
    use HasFactory;

    protected $table = 'bloqueos_agenda';

    protected $fillable = [
        'inicio_at',
        'fin_at',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'inicio_at' => 'immutable_datetime',
            'fin_at' => 'immutable_datetime',
            'anulado_at' => 'immutable_datetime',
        ];
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }
}
