<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemDeclaracionSalud extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'items_declaracion_salud';

    protected $fillable = [
        'declaracion_salud_id',
        'tipo',
        'descripcion',
    ];

    public function declaracionSalud(): BelongsTo
    {
        return $this->belongsTo(
            DeclaracionSaludPaciente::class,
            'declaracion_salud_id'
        );
    }
}
