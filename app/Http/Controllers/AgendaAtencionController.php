<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarAgendaAtencionRequest;
use App\Services\Agenda\AgendaProfesionalQueryService;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class AgendaAtencionController extends Controller
{
    public function index(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        RelojAplicacion $reloj,
    ): View|RedirectResponse {
        // Resolver desde la relación persistida, sin aceptar ids profesionales del cliente
        // ni confiar en una relación que pudiera estar precargada en el usuario.
        $kinesiologaId = $request->user('web')->kinesiologa()->value('id');
        abort_unless($kinesiologaId !== null, 403);

        $datos = $request->validated();
        $esPeriodo = isset($datos['desde']);
        $fechaInicial = $datos['desde'] ?? $datos['fecha'] ?? null;
        $fecha = $fechaInicial !== null
            ? CarbonImmutable::createFromFormat('!Y-m-d', $fechaInicial, config('app.timezone'))
            : $reloj->hoy();
        $hasta = $esPeriodo
            ? CarbonImmutable::createFromFormat('!Y-m-d', $datos['hasta'], config('app.timezone'))
            : $fecha;
        $contextoTemporal = $esPeriodo
            ? ['desde' => $fecha->toDateString(), 'hasta' => $hasta->toDateString()]
            : ['fecha' => $fecha->toDateString()];
        $filtros = array_filter(
            Arr::only($datos, ['paciente', 'hora_desde', 'hora_hasta', 'estado', 'tipo']),
            fn ($valor) => $valor !== null && $valor !== '',
        );

        // Filtrar exclusivamente la representación administrativa ya autorizada.
        // No consultar un directorio global de pacientes ni duplicar el lector del bloque B.
        $autorizados = $consulta->informacionDePeriodo((int) $kinesiologaId, $fecha, $hasta);
        $resultados = array_values(array_filter($autorizados, function (array $turno) use ($filtros): bool {
            $hora = $turno['inicio_at']->format('H:i');

            return (! isset($filtros['paciente']) || mb_stripos($turno['paciente']['nombre'] ?? '', $filtros['paciente']) !== false)
                && (! isset($filtros['hora_desde']) || $hora >= $filtros['hora_desde'])
                && (! isset($filtros['hora_hasta']) || $hora <= $filtros['hora_hasta'])
                && (! isset($filtros['estado']) || $turno['estado']->value === $filtros['estado'])
                && (! isset($filtros['tipo']) || $turno['tipo']->value === $filtros['tipo']);
        }));

        // Tamaño de presentación, sin limitar el período funcional consultable.
        $porPagina = 25;
        $ultimaPagina = max(1, (int) ceil(count($resultados) / $porPagina));
        $pagina = (int) ($datos['page'] ?? 1);
        $parametros = $contextoTemporal + $filtros;
        if ($pagina > $ultimaPagina) {
            return redirect()->route('turnos.agenda-atencion.index', $parametros + ['page' => $ultimaPagina]);
        }
        $turnos = array_slice($resultados, ($pagina - 1) * $porPagina, $porPagina);
        $paginacion = new LengthAwarePaginator($turnos, count($resultados), $porPagina, $pagina, [
            'path' => route('turnos.agenda-atencion.index'), 'query' => $parametros,
        ]);

        return view('turnos.agenda-atencion.index', [
            'fecha' => $fecha,
            'turnos' => $turnos,
            'hasta' => $hasta,
            'esPeriodo' => $esPeriodo,
            'contextoTemporal' => $contextoTemporal,
            'filtros' => $filtros,
            'paginacion' => $paginacion,
        ]);
    }
}
