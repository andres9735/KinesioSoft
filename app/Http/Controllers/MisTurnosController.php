<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarDetalleTurnoRequest;
use App\Http\Requests\FiltrarMisTurnosRequest;
use App\Models\Paciente;
use App\Models\Turno;
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

    public function show(ConsultarDetalleTurnoRequest $request, Turno $turno, MisTurnosQueryService $consultas): View
    {
        // El Form Request ejecuta Gate::authorize('view') antes de validar el contexto.
        $detalle = $consultas->detalle($turno, $request->user());
        $contexto = $consultas->contextoListado($request->filtros(), $request->only(['proximos_page', 'historicos_page']));
        $seccion = $request->validated('seccion') ?? $detalle['seccion'];

        return view('turnos.mis-turnos.show', [
            'detalle' => $detalle,
            'urlVolver' => route('turnos.mis-turnos.index', $contexto).'#'.$seccion.'-titulo',
        ]);
    }
}
