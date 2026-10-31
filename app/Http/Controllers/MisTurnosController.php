<?php

namespace App\Http\Controllers;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Exceptions\Turnos\ConfirmacionAsistenciaNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Http\Requests\CancelarTurnoRequest;
use App\Http\Requests\ConfirmarAsistenciaTurnoRequest;
use App\Http\Requests\ConsultarDetalleTurnoRequest;
use App\Http\Requests\FiltrarMisTurnosRequest;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use App\Services\Turnos\MisTurnosQueryService;
use App\Services\Turnos\ReferenciaProgramacionTurnoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

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

    public function show(ConsultarDetalleTurnoRequest $request, Turno $turno, MisTurnosQueryService $consultas, ReferenciaProgramacionTurnoService $referencias): View
    {
        // El Form Request ejecuta Gate::authorize('view') antes de validar el contexto.
        $detalle = $consultas->detalle($turno, $request->user());
        $contexto = $consultas->contextoListado($request->filtros(), $request->only(['proximos_page', 'historicos_page']));
        $seccion = $request->validated('seccion') ?? $detalle['seccion'];

        return view('turnos.mis-turnos.show', [
            'detalle' => $detalle,
            'urlVolver' => route('turnos.mis-turnos.index', $contexto).'#'.$seccion.'-titulo',
            'autogestion' => [
                'referencia' => $referencias->emitir($turno, $request->user()),
                'urlConfirmar' => route('turnos.mis-turnos.confirmar', $turno),
                'urlCancelar' => route('turnos.mis-turnos.cancelar', $turno),
                'urlDetalle' => route('turnos.mis-turnos.show', ['turno' => $turno, 'seccion' => $seccion] + $contexto),
                'resumen' => ($detalle['tipo']->value === 'PRIMERA_CONSULTA' ? 'Primera consulta' : 'Seguimiento')
                    .' con '.($detalle['profesional'] ?: 'profesional no disponible').', el '
                    .$detalle['inicio_at']->format('d/m/Y').' de '.$detalle['inicio_at']->format('H:i')
                    .' a '.$detalle['fin_at']->format('H:i').' hs.',
            ],
        ]);
    }

    public function confirmar(ConfirmarAsistenciaTurnoRequest $request, Turno $turno): JsonResponse
    {
        return $this->operar($request, $turno, false);
    }

    public function cancelar(CancelarTurnoRequest $request, Turno $turno): JsonResponse
    {
        return $this->operar($request, $turno, true);
    }

    private function operar(ConfirmarAsistenciaTurnoRequest $request, Turno $turno, bool $cancelar): JsonResponse
    {
        try {
            app(ReferenciaProgramacionTurnoService::class)->comprobar($request->validated('referencia_programacion'), $turno, $request->user());
            $resultado = $cancelar
                ? app(CancelarTurnoAction::class)->ejecutar($request->user(), $turno, $request->validated('motivo'))
                : app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($request->user(), $turno);

            return response()->json([
                'code' => $cancelar ? 'turno_cancelado' : 'asistencia_confirmada',
                'es_replay' => $resultado->esReplay,
                'message' => $resultado->esReplay
                    ? ($cancelar ? 'El turno ya estaba cancelado.' : 'La asistencia ya estaba confirmada.')
                    : ($cancelar ? 'El turno fue cancelado.' : 'Confirmaste tu intención de asistir.'),
            ]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (ProgramacionTurnoModificadaException|RecursosTurnoModificadosException) {
            return response()->json(['code' => 'programacion_modificada', 'message' => 'La programación cambió. Actualizá el detalle antes de continuar.'], 409);
        } catch (ConfirmacionAsistenciaNoPermitidaException|CancelacionTurnoNoPermitidaException $error) {
            return response()->json(['code' => $error->motivo, 'message' => $error->getMessage()], 422);
        } catch (AuthorizationException) {
            return response()->json(['code' => 'no_autorizado', 'message' => 'No tenés autorización para esta operación.'], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['code' => 'turno_no_disponible', 'message' => 'El turno ya no está disponible.'], 404);
        } catch (Throwable $error) {
            report($error);

            return response()->json(['code' => 'error_tecnico', 'message' => 'No pudimos completar la operación. Consultá el estado actual antes de intentarlo nuevamente.'], 500);
        }
    }
}
