<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Antropometria extends Model
{
    protected $table = 'antropometria';
    protected $primaryKey = 'antropometria_id';

    protected $fillable = [
        'entrada_hc_id',
        'fecha',
        'altura_cm',
        'peso_kg',
    ];

    protected $casts = [
        'fecha'     => 'date',
        'altura_cm' => 'decimal:2',
        'peso_kg'   => 'decimal:2',
    ];

    protected $appends = ['imc'];

    public function entradaHc(): BelongsTo
    {
        return $this->belongsTo(EntradaHc::class, 'entrada_hc_id', 'entrada_hc_id');
    }

    /** IMC = kg / (m^2). Devuelve null si falta algún dato. */
    public function getImcAttribute(): ?float
    {
        if (empty($this->peso_kg) || empty($this->altura_cm) || $this->altura_cm <= 0) {
            return null;
        }

        $m = (float) $this->altura_cm / 100.0;
        if ($m <= 0) {
            return null;
        }

        return round((float) $this->peso_kg / ($m * $m), 2);
    }
}
