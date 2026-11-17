<?php

namespace App\Http\Controllers;

use App\Actions\Turnos\AceptarOfertaAdelantoAction;
use App\Jobs\RevisarCicloOfertaAdelantoJob;
use App\Models\OfertaAdelanto;
use App\Services\Turnos\CerrarOfertaAdelantoService;
use App\Services\Turnos\ConsultarOfertaAdelantoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class OfertaAdelantoController extends Controller
{
    public function mostrar(Request $request): Response
    {
        $acceso = $request->attributes->get('adelanto.acceso');

        return response()->view('turnos.adelanto.show', [
            'acceso' => $acceso, 'csrf' => $request->attributes->get('adelanto.autorizacion')['csrf'] ?? null,
        ], in_array($acceso['estado'], [OfertaAdelanto::ACTIVA, OfertaAdelanto::ACEPTADA], true) ? 200 : 410);
    }

    public function rechazar(Request $request, CerrarOfertaAdelantoService $cierres, ConsultarOfertaAdelantoService $consultas): Response
    {
        $autorizacion = $request->attributes->get('adelanto.autorizacion');
        $salida = $cierres->cerrar($autorizacion['oferta'], OfertaAdelanto::RECHAZADA, $autorizacion);
        if ($salida->continuacion === 'pendiente') {
            RevisarCicloOfertaAdelantoJob::dispatch($autorizacion['oferta'])->onConnection('database')->afterCommit();
        }

        return response()->view('turnos.adelanto.show', [
            'acceso' => $consultas->resultadoDe($salida), 'csrf' => null,
        ], in_array($salida->resultado, [OfertaAdelanto::RECHAZADA, OfertaAdelanto::ACEPTADA], true) ? 200 : 410);
    }

    public function aceptar(Request $request, AceptarOfertaAdelantoAction $accion, ConsultarOfertaAdelantoService $consultas): Response
    {
        $autorizacion = $request->attributes->get('adelanto.autorizacion');
        try {
            $salida = $accion->ejecutar($autorizacion['oferta'], $autorizacion);
        } catch (AuthorizationException $error) {
            throw $error;
        } catch (Throwable) {
            Log::error('No se pudo completar el traslado de una oferta de adelanto.');

            return response()->view('turnos.adelanto.show', ['acceso' => ['estado' => 'traslado_fallido'], 'csrf' => null], 503);
        }
        if ($salida->continuacion === 'pendiente') {
            RevisarCicloOfertaAdelantoJob::dispatch($autorizacion['oferta'])->onConnection('database')->afterCommit();
        }

        return response()->view('turnos.adelanto.show', [
            'acceso' => $consultas->resultadoDe($salida), 'csrf' => null,
        ], in_array($salida->resultado, [OfertaAdelanto::ACEPTADA, OfertaAdelanto::RECHAZADA], true) ? 200 : 410);
    }
}
