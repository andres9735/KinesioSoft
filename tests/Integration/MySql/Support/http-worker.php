<?php

use App\Models\Turno;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Integration\MySql\Support\Barrera;
use Tests\Integration\MySql\Support\EntornoMariaDb;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$temporales = [];
$sesion = null;
$intentos = 0;
try {
    $entrada = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $app = EntornoMariaDb::aplicacion();
    EntornoMariaDb::configurarStorage($entrada['escenario']);
    config(['app.debug' => false, 'logging.default' => 'null']);
    $preflight = EntornoMariaDb::verificar(DB::connection());
    $sesion = (int) $preflight['sesion'];
    $nombre = $entrada['worker'];
    if (! preg_match('/\A[a-z]+\z/', $nombre)) {
        throw new RuntimeException('Worker inválido.');
    }
    $barrera = new Barrera(storage_path('framework/testing/uc07-mysql/'.$entrada['escenario'].'/barreras'));
    DB::statement('SET SESSION innodb_lock_wait_timeout = '.(int) ($entrada['lock_timeout'] ?? 15));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));

    // Habilitar la comprobación CSRF que Laravel omite normalmente en testing.
    // Sólo este proceso de test; no se reemplaza ni se omite middleware de producción.
    $app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
    $session = $app['session']->driver();
    $session->start();
    $session->put(Auth::guard('web')->getName(), $entrada['actor']);
    $token = $session->token();
    $session->save();
    $cookie = config('session.cookie');
    $valor = CookieValuePrefix::create($cookie, $app['encrypter']->getKey()).$session->getId();
    $cookies = [$cookie => $app['encrypter']->encrypt($valor, false)];
    Auth::forgetGuards();
    $kernel = $app->make(Kernel::class);
    $codigosSql = [];
    $app->make(ExceptionHandler::class)->reportable(function (Throwable $error) use (&$codigosSql): void {
        for ($causa = $error; $causa !== null; $causa = $causa->getPrevious()) {
            if ($causa instanceof PDOException) {
                $codigosSql[] = (int) ($causa->errorInfo[1] ?? 0);
            }
        }
    });
    $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'multipart/form-data; boundary=uc07-harness'];
    $invalid = Request::create('/turnos/solicitar', 'POST', ['clave_idempotencia' => $entrada['uuid']], $cookies, [], $server);
    $denegada = $kernel->handle($invalid);
    $csrfStatus = $denegada->getStatusCode();
    $kernel->terminate($invalid, $denegada);
    if ($csrfStatus !== 419) {
        throw new RuntimeException('El recorrido debe verificar CSRF real.');
    }
    // El intento sin token no ha ejecutado la Action ni creado filas.
    $barrera->marcar($nombre.'-listo', [
        'sesion' => $sesion, 'aislamiento' => $preflight['aislamiento'],
        'csrf_rechazado' => $csrfStatus, 'sesion_http_hash' => hash('sha256', $session->getId()),
    ]);
    $barrera->esperar($nombre.'-iniciar');
    Event::listen(TransactionBeginning::class, function () use (&$intentos): void {
        $intentos++;
    });
    if ($entrada['snapshot'] ?? false) {
        $capturado = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$capturado, $barrera, $nombre): void {
            if (! $capturado && str_contains($sql, 'from '.DB::connection()->getQueryGrammar()->wrapTable('kinesiologas'))
                && str_contains($sql, 'for update')) {
                $capturado = true;
                foreach (['turnos', 'users', 'franjas_horarias_agenda', 'bloqueos_agenda'] as $tabla) {
                    DB::table($tabla)->count();
                }
                $barrera->marcar($nombre.'-snapshot');
            }
        });
    }
    Turno::creating(function () use ($entrada, $barrera, $nombre): void {
        $barrera->marcar($nombre.'-antes-insertar');
        if ($entrada['pausar'] ?? false) {
            $barrera->esperar($nombre.'-continuar');
        }
    });
    $archivos = [];
    for ($i = 0; $i < ($entrada['archivos'] ?? 0); $i++) {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-http-mysql-');
        $temporales[] = $ruta;
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% ".$nombre.'-'.$i."\n%%EOF\n");
        $archivos[] = new UploadedFile($ruta, $nombre.'-'.$i.'.pdf', 'application/octet-stream', UPLOAD_ERR_OK, true);
    }
    $payload = [
        '_token' => $token, 'clave_idempotencia' => $entrada['uuid'], 'kinesiologa_id' => $entrada['kinesiologa'],
        'inicio_at' => $entrada['inicio_at'] ?? '2026-10-05 10:00:00', 'motivo_solicitud' => 'Motivo privado del fixture',
    ];
    $request = Request::create('/turnos/solicitar', 'POST', $payload, $cookies,
        $archivos === [] ? [] : ['adjuntos' => $archivos], $server);
    $respuesta = $kernel->handle($request);
    $body = json_decode($respuesta->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $kernel->terminate($request, $respuesta);
    $salida = [
        'status' => 'http', 'http_status' => $respuesta->getStatusCode(), 'code' => $body['code'] ?? null,
        'turno_id' => $body['turno_id'] ?? null, 'esReplay' => $body['es_replay'] ?? null,
        'redirect_url' => $body['redirect_url'] ?? null, 'csrf_rechazado' => $csrfStatus,
        'sesion_http_hash' => hash('sha256', $session->getId()),
        'multipart' => $request->isMethod('POST') && str_starts_with($request->header('Content-Type'), 'multipart/form-data'),
        'archivos_recibidos' => count($request->file('adjuntos', [])),
        'codigos_sql' => array_values(array_unique($codigosSql)),
        'deadlock_observado' => in_array(1213, $codigosSql, true),
    ];
} catch (Throwable $error) {
    // No imprimir SQL, motivo, cookies, tokens ni payload.
    $salida = ['status' => 'harness_error', 'excepcion' => $error::class];
} finally {
    foreach ($temporales as $ruta) {
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }
}
$salida += ['sesion' => $sesion, 'intentos' => $intentos, 'finalizado_at' => microtime(true)];
if (($entrada['pausar_final'] ?? false) && isset($barrera)) {
    $barrera->marcar($nombre.'-finalizada', $salida);
    $barrera->esperar($nombre.'-salir');
}
echo json_encode($salida, JSON_THROW_ON_ERROR), PHP_EOL;
exit($salida['status'] === 'http' ? 0 : 2);
