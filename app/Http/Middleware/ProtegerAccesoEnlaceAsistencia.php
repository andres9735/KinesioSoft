<?php

namespace App\Http\Middleware;

use App\Exceptions\Turnos\AccesoEnlaceRechazadoException;
use App\Models\EnlaceAsistenciaTurno;
use App\Services\Turnos\AccesoEnlaceAsistencia;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\ResolverAsistenciaEnlaceService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ProtegerAccesoEnlaceAsistencia
{
    public function __construct(private ValidarEnlaceAsistenciaTurnoService $validador, private AutorizacionNavegadorEnlaceService $navegador) {}

    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        $token = $request->query('token');
        $operacion = $request->query('operacion');
        // InputBag::get rechaza arrays antes del saneamiento. Leer sin interpretar
        // y comprobar el tipo dentro del recorrido protegido también para cookies hostiles.
        $cookies = $request->cookies->all();
        $cookie = $cookies[AutorizacionNavegadorEnlaceService::COOKIE] ?? '';
        $recibo = $cookies[AutorizacionNavegadorEnlaceService::RESULTADO] ?? '';
        $recuperacion = is_string($operacion) && Str::isUuid($operacion)
            ? ($cookies[AutorizacionNavegadorEnlaceService::nombreRecuperacion($operacion)] ?? '') : '';
        unset($cookies);
        $csrf = $request->input('_csrf_enlace');
        $decision = $request->input('accion');
        $acepto = $request->input('acepto');
        // Esta ruta no utiliza sesión. Eliminar la URL secreta del Request antes
        // de validación, vistas, errores o cualquier registro posterior de aplicación.
        $server = $request->server->all();
        $server['REQUEST_URI'] = $request->getBaseUrl().$request->getPathInfo();
        $server['QUERY_STRING'] = '';
        unset($server['HTTP_REFERER'], $server['HTTP_COOKIE'], $server['HTTP_AUTHORIZATION']);
        // Metadatos administrativos permitidos; omitir posibles secretos de cabeceras.
        $server['HTTP_USER_AGENT'] = substr(preg_replace('/[A-Za-z0-9+\/_=-]{40,}|[\r\n]/', '[dato omitido]', (string) ($server['HTTP_USER_AGENT'] ?? '')), 0, 200);
        $request->initialize([], [], $request->attributes->all(), [], [], $server, '');
        $request->setJson(new InputBag);
        try {
            if (! $request->isSecure() || $request->getSchemeAndHttpHost() !== $this->origen()) {
                $response = new Response('Utilizá el enlace HTTPS recibido por correo.', 400);
            } else {
                $nuevaCookie = null;
                $recuperaciones = [];
                if ($request->isMethod('POST')) {
                    $datos = $this->navegador->leer(is_string($cookie) ? $cookie : '');
                    if (! is_string($csrf) || ! hash_equals($datos['csrf'], $csrf)
                        || $request->headers->get('Origin') !== $this->origen()
                        || ! in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin'], true)) {
                        throw new AccesoEnlaceRechazadoException('csrf');
                    }
                    $clave = 'uc28-post:'.hash('sha256', $datos['enlace'].'|'.$request->ip());
                    if (RateLimiter::tooManyAttempts($clave, 10)) {
                        throw new AccesoEnlaceRechazadoException('limite');
                    }
                    RateLimiter::hit($clave, 60);
                    $request->attributes->set('uc28.autorizacion', $datos);
                    $request->attributes->set('uc28.decision', is_string($decision) ? $decision : '');
                    $request->attributes->set('uc28.acepto', $acepto === '1');
                } elseif ($request->routeIs('turnos.asistencia.resultado')) {
                    if ($operacion !== null) {
                        $datos = $this->navegador->leer(is_string($recuperacion) ? $recuperacion : '', 'uc28-recuperacion-v1');
                        if (($datos['operacion'] ?? null) !== $operacion) {
                            throw new AccesoEnlaceRechazadoException;
                        }
                        $request->attributes->set('uc28.resultado', app(ResolverAsistenciaEnlaceService::class)->recuperar($datos));
                    } else {
                        $request->attributes->set('uc28.resultado', $this->navegador->leer(is_string($recibo) ? $recibo : '', 'uc28-resultado-v1'));
                    }
                } else {
                    $datos = null;
                    if ($token !== null) {
                        $acceso = $this->validador->validar(is_string($token) ? $token : '');
                        if ($acceso->estado === AccesoEnlaceAsistencia::VALIDO && ! $request->isMethod('HEAD')) {
                            $enlace = EnlaceAsistenciaTurno::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
                            [$datos, $nuevaCookie] = $this->navegador->crear($enlace);
                            $recuperaciones = $this->navegador->recuperaciones($datos);
                        }
                    } else {
                        $datos = $this->navegador->leer(is_string($cookie) ? $cookie : '');
                        $enlace = EnlaceAsistenciaTurno::query()->useWritePdo()->find($datos['enlace']);
                        if ($enlace === null || $enlace->version_programacion !== $datos['revision']) {
                            throw new AccesoEnlaceRechazadoException;
                        }
                        $acceso = $this->validador->validarReferencia($enlace);
                    }
                    $request->attributes->set(AccesoEnlaceAsistencia::class, $acceso);
                    $request->attributes->set('uc28.csrf', $datos['csrf'] ?? null);
                    $request->attributes->set('uc28.operaciones', $datos['operaciones'] ?? []);
                }
                unset($token);
                $response = $next($request);
                if ($nuevaCookie !== null) {
                    $response->headers->setCookie($nuevaCookie);
                }
                foreach ($recuperaciones as $comprobante) {
                    $response->headers->setCookie($comprobante);
                }
            }
        } catch (AccesoEnlaceRechazadoException $error) {
            $status = match ($error->motivo) {
                'csrf' => 419, 'limite' => 429, 'vencido' => 410, default => 403
            };
            $response = response()->view('turnos.asistencia.resultado', ['codigo' => $error->motivo, 'accion' => '', 'replay' => false], $status);
        } catch (Throwable) {
            // No reportar el objeto de excepción ni el Request: pueden transportar secretos.
            Log::error('No se pudo consultar el enlace de asistencia.');
            $response = new Response('No pudimos consultar el enlace. Intentá nuevamente o comunicate con el consultorio.', 503);
        }
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        // Sólo el controlador de la página con formulario habilita strict-origin.
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

        return $response;
    }

    private function origen(): string
    {
        $partes = parse_url((string) config('app.url'));

        if (! is_array($partes) || strtolower($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])) {
            return '';
        }

        return 'https://'.strtolower($partes['host']).(isset($partes['port']) && $partes['port'] !== 443 ? ':'.$partes['port'] : '');
    }
}
