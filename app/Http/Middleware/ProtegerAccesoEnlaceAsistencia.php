<?php

namespace App\Http\Middleware;

use App\Services\Turnos\AccesoEnlaceAsistencia;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ProtegerAccesoEnlaceAsistencia
{
    public function __construct(private ValidarEnlaceAsistenciaTurnoService $validador) {}

    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        $token = $request->query('token');
        // Esta ruta no utiliza sesión. Eliminar la URL secreta del Request antes
        // de validación, vistas, errores o cualquier registro posterior de aplicación.
        $server = $request->server->all();
        $server['REQUEST_URI'] = $request->getBaseUrl().$request->getPathInfo();
        $server['QUERY_STRING'] = '';
        unset($server['HTTP_REFERER']);
        $request->initialize([], [], $request->attributes->all(), [], [], $server);
        try {
            if (! $request->isSecure()) {
                $response = new Response('Utilizá el enlace HTTPS recibido por correo.', 400);
            } else {
                $request->attributes->set(AccesoEnlaceAsistencia::class, $this->validador->validar(is_string($token) ? $token : ''));
                unset($token);
                $response = $next($request);
            }
        } catch (Throwable) {
            // No reportar el objeto de excepción ni el Request: pueden transportar secretos.
            Log::error('No se pudo consultar el enlace de asistencia.');
            $response = new Response('No pudimos consultar el enlace. Intentá nuevamente o comunicate con el consultorio.', 503);
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");

        return $response;
    }
}
