<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calle extends Model
{
    use HasFactory;

    protected $table = 'calles';

    protected $fillable = [
        'georef_id',
        'localidad_id',
        'nombre',
    ];

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(Localidad::class);
    }
}
