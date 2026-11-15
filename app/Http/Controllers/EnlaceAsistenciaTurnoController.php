<?php

namespace App\Http\Controllers;

use App\Exceptions\Turnos\AccesoEnlaceRechazadoException;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Exceptions\Turnos\ConfirmacionAsistenciaNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Services\Turnos\AccesoEnlaceAsistencia;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\ResolverAsistenciaEnlaceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

final class EnlaceAsistenciaTurnoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $acceso = $request->attributes->get(AccesoEnlaceAsistencia::class);
        abort_unless($acceso instanceof AccesoEnlaceAsistencia, 500);
        $status = match ($acceso->estado) {
            AccesoEnlaceAsistencia::VALIDO => 200,
            AccesoEnlaceAsistencia::VENCIDO, AccesoEnlaceAsistencia::REVOCADO => 410,
            AccesoEnlaceAsistencia::PROGRAMACION_MODIFICADA, AccesoEnlaceAsistencia::ESTADO_INCOMPATIBLE => 409,
            default => 403,
        };

        $csrf = $request->attributes->get('uc28.csrf');
        // El formulario necesita Origin sin divulgar la ruta ni la credencial en Referer.
        $referrerPolicy = $status === 200 && is_string($csrf) && $csrf !== ''
            && ($acceso->puedeConfirmar || $acceso->puedeCancelar) ? 'strict-origin' : 'no-referrer';

        return response()->view('turnos.asistencia.show', [
            'acceso' => $acceso, 'csrf' => $csrf, 'referrerPolicy' => $referrerPolicy,
            'operaciones' => $request->attributes->get('uc28.operaciones', []),
        ], $status)->header('Referrer-Policy', $referrerPolicy);
    }

    public function decidir(Request $request, ResolverAsistenciaEnlaceService $resolver, AutorizacionNavegadorEnlaceService $navegador): RedirectResponse
    {
        $datos = $request->attributes->get('uc28.autorizacion');
        $accion = $request->attributes->get('uc28.decision');
        $resultado = ['accion' => '', 'replay' => false, 'codigo' => 'decision_invalida'];
        if (is_array($datos) && in_array($accion, ['confirmar', 'cancelar'], true) && $request->attributes->get('uc28.acepto') === true) {
            try {
                $operacion = $datos['operaciones'][$accion] ?? null;
                if (! is_string($operacion)) {
                    throw new AccesoEnlaceRechazadoException;
                }
                $resultado = $resolver->ejecutar($datos, $accion, $operacion);
            } catch (AccesoEnlaceRechazadoException $error) {
                $resultado['codigo'] = $error->motivo;
            } catch (CancelacionTurnoNoPermitidaException $error) {
                $resultado['codigo'] = $error->motivo === 'fuera_de_plazo' ? 'cancelacion_fuera_de_plazo' : 'estado_o_programacion';
            } catch (ConfirmacionAsistenciaNoPermitidaException $error) {
                $resultado['codigo'] = $error->motivo === 'inicio_no_futuro' ? 'vencido' : 'estado_o_programacion';
            } catch (AuthorizationException) {
                $resultado['codigo'] = 'invalido';
            } catch (ProgramacionTurnoModificadaException|RecursosTurnoModificadosException) {
                $resultado['codigo'] = 'programacion_modificada';
            } catch (Throwable) {
                Log::error('No se pudo resolver la asistencia mediante enlace.');
                $resultado['codigo'] = 'error_tecnico';
            }
        }
        $response = new RedirectResponse('/turnos/asistencia/resultado', 303);
        $response->headers->setCookie($navegador->resultado($datos['enlace'], $resultado));

        return $response;
    }

    public function resultado(Request $request): Response
    {
        $datos = $request->attributes->get('uc28.resultado');

        return response()->view('turnos.asistencia.resultado', [
            'codigo' => $datos['codigo'] ?? 'error_tecnico',
            'accion' => $datos['accion'] ?? '', 'replay' => $datos['replay'] ?? false,
        ]);
    }
}
