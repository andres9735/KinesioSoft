<?php

namespace App\Observers;

use App\Models\Valoracion;

class ValoracionObserver
{
    public function saved(Valoracion $v): void
    {
        $prof = $v->profesional()->select('id')->first();
        if (!$prof) return;

        $agg = Valoracion::where('profesional_id', $prof->id)
            ->selectRaw('AVG(puntuacion) as a, COUNT(*) as c')
            ->first();

        $prof->update([
            'rating_avg'   => round((float)($agg->a ?? 0), 2),
            'rating_count' => (int)($agg->c ?? 0),
        ]);
    }

    public function deleted(Valoracion $v): void
    {
        // Recalcular también al borrar
        $prof = $v->profesional()->select('id')->first();
        if (!$prof) return;

        $agg = Valoracion::where('profesional_id', $prof->id)
            ->selectRaw('AVG(puntuacion) as a, COUNT(*) as c')
            ->first();

        $prof->update([
            'rating_avg'   => round((float)($agg->a ?? 0), 2),
            'rating_count' => (int)($agg->c ?? 0),
        ]);
    }
}
