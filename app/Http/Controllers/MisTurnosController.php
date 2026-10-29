<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltrarMisTurnosRequest;
use App\Models\Paciente;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use App\Services\Turnos\MisTurnosQueryService;
use Illuminate\View\View;

final class MisTurnosController extends Controller
{
    public function index(FiltrarMisTurnosRequest $request, MisTurnosQueryService $consultas, ElegibilidadPrimeraConsultaService $elegibilidad): View
    {
        $paciente = $request->user()->paciente()->first();
        abort_unless($paciente instanceof Paciente, 403);

        $filtros = $request->filtros();
        $navegacion = $request->input('aplicar') === '1' ? [] : $request->only(['proximos_page', 'historicos_page', 'turno']);

        return view('turnos.mis-turnos.index', $consultas->listar($paciente, $filtros, $navegacion) + [
            'filtros' => $filtros,
            'primeraConsultaActiva' => $elegibilidad->tienePrimeraConsultaFuturaActiva($paciente),
        ]);
    }
}
