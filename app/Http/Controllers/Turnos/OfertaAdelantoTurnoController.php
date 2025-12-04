<?php

namespace App\Http\Controllers\Turnos;

use App\Http\Controllers\Controller;
use App\Models\OfertaAdelantoTurno;
use App\Services\AsignacionAutomaticaDeTurnosService;
use Illuminate\Http\Request;

class OfertaAdelantoTurnoController extends Controller
{
    public function __construct(
        protected AsignacionAutomaticaDeTurnosService $service
    ) {}

    /**
     * Maneja el click desde el mail de oferta de adelanto.
     *
     * GET /oferta-adelanto/{token}?accion=aceptar|rechazar
     */
    public function __invoke(Request $request, string $token)
    {
        $accion = $request->query('accion');

        if (! in_array($accion, ['aceptar', 'rechazar'], true)) {
            return view('turnos.oferta-adelanto-resultado', [
                'estado' => 'accion_invalida',
            ]);
        }

        $oferta = OfertaAdelantoTurno::where('oferta_token', $token)->first();

        if (! $oferta) {
            return view('turnos.oferta-adelanto-resultado', [
                'estado' => 'no_encontrada',
            ]);
        }

        if ($accion === 'aceptar') {
            $ofertaActualizada = $this->service->aceptarOferta($oferta);
        } else {
            $ofertaActualizada = $this->service->rechazarOferta($oferta);
        }

        return view('turnos.oferta-adelanto-resultado', [
            'estado' => $ofertaActualizada->estado,
        ]);
    }
}
