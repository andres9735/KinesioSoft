<?php

namespace App\Http\Middleware;

use App\Models\OfertaAdelanto;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\ConsultarOfertaAdelantoService;
use App\Services\Turnos\PrepararDespachoOfertaAdelantoService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ProtegerAccesoOfertaAdelanto
{
    public const COOKIE = '__Secure-ks_adelanto';

    public function __construct(private readonly ConsultarOfertaAdelantoService $consultas, private readonly PrepararDespachoOfertaAdelantoService $origen, private readonly RelojAplicacion $reloj) {}

    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        $query = $request->query->all();
        $token = $query['token'] ?? null;
        $cookie = $request->cookies->all()[self::COOKIE] ?? null;
        $entrada = $request->request->all();
        $server = $request->server->all();
        $server['REQUEST_URI'] = $request->getBaseUrl().$request->getPathInfo();
        $server['QUERY_STRING'] = '';
        unset($server['HTTP_REFERER'], $server['HTTP_COOKIE'], $server['HTTP_AUTHORIZATION'], $server['HTTP_USER_AGENT']);
        // La credencial y cookies no alcanzan vistas, excepciones ni registros posteriores.
        $request->initialize([], [], $request->attributes->all(), [], [], $server, '');
        $request->setJson(new InputBag);
        try {
            $base = $this->origen->baseSegura();
            $host = 'https://'.parse_url($base, PHP_URL_HOST);
            $puerto = parse_url($base, PHP_URL_PORT);
            $host .= $puerto !== null && $puerto !== 443 ? ':'.$puerto : '';
            if (! $request->isSecure() || $request->getSchemeAndHttpHost() !== $host) {
                return $this->proteger(new Response('Utilizá el enlace HTTPS recibido por correo.', 400));
            }
            $clave = 'adelanto:'.hash('sha256', ($request->isMethod('POST') ? 'post:' : 'get:').$request->ip());
            if (RateLimiter::tooManyAttempts($clave, $request->isMethod('POST') ? 20 : 120)) {
                return $this->proteger(new Response('Intentá nuevamente en un minuto.', 429));
            }
            RateLimiter::hit($clave, 60);
            $datos = null;
            if ($request->isMethod('POST') || $token === null) {
                try {
                    $datos = is_string($cookie) ? json_decode(Crypt::decryptString($cookie), true, 16, JSON_THROW_ON_ERROR) : null;
                } catch (Throwable) {
                    $datos = null;
                }
                if (! is_array($datos) || ($datos['uso'] ?? null) !== 'adelanto-navegador-v1'
                    || ! is_int($datos['exp'] ?? null) || $this->reloj->ahora()->getTimestamp() >= $datos['exp']
                    || ! is_int($datos['despacho'] ?? null) || $datos['despacho'] <= 0
                    || ! is_int($datos['oferta'] ?? null) || $datos['oferta'] <= 0
                    || ! is_int($datos['paciente'] ?? null) || $datos['paciente'] <= 0
                    || ! is_string($datos['csrf'] ?? null)) {
                    return $this->proteger(new Response('El enlace no es válido. Usá el enlace recibido por correo.', 403));
                }
                if ($request->isMethod('POST') && (! is_string($entrada['_csrf_oferta'] ?? null)
                    || ! hash_equals($datos['csrf'], $entrada['_csrf_oferta'])
                    || ($entrada['accion'] ?? null) !== ($request->routeIs('turnos.adelanto.aceptar') ? 'aceptar' : 'rechazar')
                    || $request->headers->get('Origin') !== $host
                    || ! in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin'], true))) {
                    return $this->proteger(new Response('No se pudo verificar la decisión. Volvé a abrir el enlace.', 419));
                }
                $acceso = $this->consultas->porReferencia($datos);
            } else {
                $acceso = $this->consultas->porToken($token);
            }
            unset($token, $cookie, $entrada, $query);
            if ($acceso === null) {
                return $this->proteger(new Response('El enlace no es válido. Usá el enlace recibido por correo.', 403));
            }
            $nueva = null;
            if ($datos === null && $acceso['estado'] === OfertaAdelanto::ACTIVA && ! $request->isMethod('HEAD')) {
                $datos = ['uso' => 'adelanto-navegador-v1', 'despacho' => $acceso['despacho'], 'oferta' => $acceso['oferta'],
                    'paciente' => $acceso['paciente'], 'csrf' => bin2hex(random_bytes(32)), 'exp' => $this->reloj->ahora()->getTimestamp() + 900];
                $nueva = new Cookie(self::COOKIE, Crypt::encryptString(json_encode($datos, JSON_THROW_ON_ERROR)), $datos['exp'],
                    rtrim((string) parse_url($base, PHP_URL_PATH), '/').'/turnos/adelanto', null, true, true, false, 'strict');
            }
            $request->attributes->set('adelanto.acceso', $acceso);
            $request->attributes->set('adelanto.autorizacion', $datos);
            $response = $next($request);
            if ($nueva !== null) {
                $response->headers->setCookie($nueva);
            }

            return $this->proteger($response);
        } catch (AuthorizationException) {
            return $this->proteger(new Response('El enlace no es válido. Usá el enlace recibido por correo.', 403));
        } catch (Throwable) {
            Log::error('No se pudo resolver el acceso a una oferta de adelanto.');

            return $this->proteger(new Response('No pudimos consultar la oferta. Intentá nuevamente.', 503));
        }
    }

    private function proteger(Response $respuesta): Response
    {
        $respuesta->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $respuesta->headers->set('Pragma', 'no-cache');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin');
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

        return $respuesta;
    }
}
