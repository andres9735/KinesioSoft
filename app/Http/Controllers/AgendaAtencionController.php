<?php

namespace App\Http\Controllers;

use App\Actions\Agenda\ExportarAgendaAtencionPdfAction;
use App\Http\Requests\ConsultarAgendaAtencionRequest;
use App\Services\Agenda\AgendaProfesionalQueryService;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Throwable;

class AgendaAtencionController extends Controller
{
    public function show(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        string $turno,
    ): Response {
        $kinesiologaId = $request->user('web')->kinesiologa()->value('id');
        abort_unless($kinesiologaId !== null, 403);
        try {
            $detalle = $consulta->informacionDeTurno((int) $kinesiologaId, (int) $turno);
        } catch (QueryException) {
            return $this->errorExportacion('turnos.agenda-atencion.show', ['turno' => $turno] + $request->validated(), true);
        }
        abort_if($detalle === null, 404);

        $contexto = array_filter($request->validated(), fn ($valor) => $valor !== null && $valor !== '');
        if (! isset($contexto['fecha']) && ! isset($contexto['desde'])) {
            $contexto['fecha'] ??= $detalle['inicio_at']->toDateString();
        }

        $modal = $request->header('X-Agenda-Detalle') === 'modal';
        $respuesta = response()->view($modal ? 'turnos.agenda-atencion.detalle' : 'turnos.agenda-atencion.show', [
            'turno' => $detalle, 'contexto' => $contexto,
        ])->header('Cache-Control', 'private, no-store')->header('Vary', 'X-Agenda-Detalle');

        return $modal ? $respuesta->header('X-Agenda-Detalle', 'administrativo') : $respuesta;
    }

    public function index(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        RelojAplicacion $reloj,
    ): View|RedirectResponse|Response {
        $lectura = $this->consultarConRecuperacion($request, $consulta, $reloj, 'turnos.agenda-atencion.index');
        if ($lectura instanceof Response) {
            return $lectura;
        }
        ['resultados' => $resultados, 'parametros' => $parametros] = $lectura;
        // Tamaño de presentación, sin limitar el período funcional consultable.
        $porPagina = 25;
        $ultimaPagina = max(1, (int) ceil(count($resultados) / $porPagina));
        $pagina = (int) ($request->validated('page') ?? 1);
        if ($pagina > $ultimaPagina) {
            return redirect()->route('turnos.agenda-atencion.index', $parametros + ['page' => $ultimaPagina]);
        }
        $turnos = array_slice($resultados, ($pagina - 1) * $porPagina, $porPagina);
        $paginacion = new LengthAwarePaginator($turnos, count($resultados), $porPagina, $pagina, [
            'path' => route('turnos.agenda-atencion.index'), 'query' => $parametros,
        ]);

        return view('turnos.agenda-atencion.index', Arr::except($lectura, ['resultados', 'parametros']) + [
            'turnos' => $turnos, 'paginacion' => $paginacion,
        ]);
    }

    public function imprimir(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        RelojAplicacion $reloj,
    ): Response {
        $lectura = $this->consultarConRecuperacion($request, $consulta, $reloj, 'turnos.agenda-atencion.imprimir');
        if ($lectura instanceof Response) {
            return $lectura;
        }
        try {
            return response()->view('turnos.agenda-atencion.exportacion', $lectura + [
                'generadoEn' => $reloj->ahora(), 'paraPdf' => false,
            ])->header('Cache-Control', 'private, no-store')
                ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
        } catch (Throwable) {
            return $this->errorExportacion('turnos.agenda-atencion.imprimir', $lectura['parametros']);
        }
    }

    public function pdf(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        RelojAplicacion $reloj,
        ExportarAgendaAtencionPdfAction $exportar,
    ): Response {
        $lectura = $this->consultarConRecuperacion($request, $consulta, $reloj, 'turnos.agenda-atencion.pdf');
        if ($lectura instanceof Response) {
            return $lectura;
        }
        $generadoEn = $reloj->ahora();
        try {
            $contenido = $exportar->ejecutar($lectura + ['generadoEn' => $generadoEn, 'paraPdf' => true]);

            return response($contenido)->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="agenda-atencion_'.$generadoEn->format('Ymd_His').'.pdf"')
                ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable) {
            return $this->errorExportacion('turnos.agenda-atencion.pdf', $lectura['parametros']);
        }
    }

    private function errorExportacion(string $ruta, array $parametros, bool $errorConsulta = false): Response
    {
        return response()->view('turnos.agenda-atencion.error-exportacion', [
            'reintentar' => route($ruta, $parametros),
            'volver' => route('turnos.agenda-atencion.index', Arr::except($parametros, ['turno'])),
            'errorConsulta' => $errorConsulta,
        ], 503)->header('Cache-Control', 'private, no-store');
    }

    private function consultarConRecuperacion(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        RelojAplicacion $reloj,
        string $ruta,
    ): array|Response {
        try {
            return $this->consultar($request, $consulta, $reloj);
        } catch (QueryException) {
            return $this->errorExportacion($ruta, $request->safe()->except('page'), true);
        }
    }

    private function consultar(
        ConsultarAgendaAtencionRequest $request,
        AgendaProfesionalQueryService $consulta,
        RelojAplicacion $reloj,
    ): array {
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

        $parametros = $contextoTemporal + $filtros;

        return [
            'fecha' => $fecha,
            'resultados' => $resultados,
            'parametros' => $parametros,
            'hasta' => $hasta,
            'esPeriodo' => $esPeriodo,
            'contextoTemporal' => $contextoTemporal,
            'filtros' => $filtros,
        ];
    }
}
