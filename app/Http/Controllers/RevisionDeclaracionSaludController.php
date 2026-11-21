<?php

namespace App\Http\Controllers;

use App\Actions\HistoriaClinica\ConfirmarRevisionDeclaracionAction;
use App\Exceptions\HistoriaClinica\ConflictoRevisionDeclaracionException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Http\Requests\ConfirmarRevisionDeclaracionRequest;
use App\Http\Requests\PrevisualizarRevisionDeclaracionRequest;
use App\Services\HistoriaClinica\ComprobanteVistaPreviaRevision;
use App\Services\HistoriaClinica\ConsultarRevisionDeclaracion;
use App\Services\HistoriaClinica\ContextoRevisionDeclaracion;
use App\Services\HistoriaClinica\PrepararResultadoRevisionDeclaracion;
use App\Services\HistoriaClinica\PresentarVistaPreviaRevision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RevisionDeclaracionSaludController extends Controller
{
    public function create(Request $request, string $turno, string $declaracion, ConsultarRevisionDeclaracion $consulta, ContextoRevisionDeclaracion $contextos): Response
    {
        try {
            $datos = $consulta->consultar($request->user(), (int) $turno, (int) $declaracion);
            $datos['contexto'] = $contextos->emitir($request->user(), $datos);

            return $this->formulario($datos);
        } catch (QueryException) {
            return $this->noDisponible($turno);
        }
    }

    public function preview(PrevisualizarRevisionDeclaracionRequest $request, string $turno, string $declaracion,
        ConsultarRevisionDeclaracion $consulta, ContextoRevisionDeclaracion $contextos,
        PrepararResultadoRevisionDeclaracion $preparador, PresentarVistaPreviaRevision $presentador,
        ComprobanteVistaPreviaRevision $comprobantes): Response
    {
        try {
            $datos = $consulta->consultar($request->user(), (int) $turno, (int) $declaracion);
            try {
                $contextos->verificar($request->validated('contexto'), $request->user(), $datos);
            } catch (ValidationException) {
                // Un paciente/contexto diferente nunca recibe el formulario anterior repoblado.
                return $this->respuesta('historia-clinica.revision-peticion-invalida', ['turnoId' => $turno], 409);
            }
            $datos['contexto'] = $request->validated('contexto');
            $entrada = json_decode($request->validated('propuesta'), true, 32);
            try {
                if (! is_array($entrada) || array_is_list($entrada)) {
                    throw ValidationException::withMessages(['propuesta' => 'El resultado debe contener los dictámenes profesionales.']);
                }
                $resultado = $preparador->preparar($datos['fuente'], $entrada, $datos['versionBase']);
                $especialidad = $datos['especialidades']->firstWhere('id', $resultado->datosClinicos['especialidad_id']);
                if (! $especialidad) {
                    throw ValidationException::withMessages(['especialidad_id' => 'Seleccioná una especialidad vigente asociada a tu perfil.']);
                }
                if (($resultado->datosClinicos['familia']['incluir_contacto'] ?? false) && ! $datos['paciente']->contacto_emergencia_nombre) {
                    throw ValidationException::withMessages(['familia' => 'No hay un contacto de emergencia identificado para confirmar.']);
                }
            } catch (ValidationException $e) {
                return $this->formulario($datos, $entrada ?? [], new MessageBag($e->errors()), 422);
            }

            return $this->respuesta('historia-clinica.revision-previa', [...$datos, 'entrada' => $entrada,
                'resultado' => $resultado, 'especialidad' => $especialidad,
                'comprobante' => $comprobantes->emitir($request->user(), $datos, $entrada, $resultado),
                'secciones' => $presentador->presentar($resultado, $datos['versionBase'], $datos['paciente'])]);
        } catch (QueryException) {
            return $this->noDisponible($turno);
        }
    }

    public function confirmar(ConfirmarRevisionDeclaracionRequest $request, string $turno, string $declaracion, ConfirmarRevisionDeclaracionAction $incorporar): Response
    {
        try {
            $resultado = $incorporar->ejecutar($request->user(), (int) $turno, (int) $declaracion, $request->validated('confirmacion'));

            return $this->respuesta('historia-clinica.revision-resultado', [...$resultado, 'turnoId' => $turno]);
        } catch (AuthorizationException) {
            return $this->errorConfirmacion($turno, 'autorizacion', 403);
        } catch (ConflictoRevisionDeclaracionException $e) {
            return $this->errorConfirmacion($turno, $e->motivo === 'version' ? 'version' : 'conflicto', 409);
        } catch (RecursosTurnoModificadosException|ModelNotFoundException) {
            return $this->errorConfirmacion($turno, 'conflicto', 409);
        } catch (ValidationException) {
            return $this->errorConfirmacion($turno, 'minimo', 422);
        } catch (QueryException) {
            return $this->errorConfirmacion($turno, 'guardado', 503);
        } catch (Throwable $e) {
            // No registrar la excepción, su mensaje, bindings ni el formulario clínico.
            Log::error('Fallo en confirmación UC-14 C.', ['tipo' => $e::class]);

            return $this->errorConfirmacion($turno, 'guardado', 500);
        }
    }

    private function errorConfirmacion(string $turno, string $tipo, int $status): Response
    {
        return $this->respuesta('historia-clinica.revision-error', ['turnoId' => $turno, 'tipo' => $tipo], $status);
    }

    private function formulario(array $datos, mixed $entrada = [], ?MessageBag $errores = null, int $status = 200): Response
    {
        return $this->respuesta('historia-clinica.revision', [...$datos,
            'entrada' => is_array($entrada) ? $entrada : [], 'erroresRevision' => $errores ?? new MessageBag], $status);
    }

    private function noDisponible(string $turno): Response
    {
        return $this->respuesta('historia-clinica.error', ['turnoId' => $turno], 503);
    }

    private function respuesta(string $vista, array $datos, int $status = 200): Response
    {
        return response()->view($vista, $datos, $status)->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
