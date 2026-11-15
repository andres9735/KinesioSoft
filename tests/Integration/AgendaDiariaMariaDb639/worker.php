<?php

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Models\Turno;
use App\Services\Agenda\GenerarAgendaDiariaService;
use App\Services\Turnos\DespacharRecordatorioTurnoService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\RegistrarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Caso639;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Entorno639;
use Tests\Integration\MySql\Support\Barrera;

require dirname(__DIR__, 3).'/vendor/autoload.php';

try {
    $app = Entorno639::aplicacion(); // Mismas guardas, usuario limitado y procedencia que el padre.
    $in = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! preg_match('/\A[a-z]+\z/', $in['nombre'] ?? '')
        || ! in_array($in['operacion'] ?? '', ['confirmar', 'cancelar', 'cancelar_enlace', 'emitir', 'registrar', 'despachar', 'generar'], true)) {
        throw new RuntimeException;
    }
    // El padre y el hijo tienen storage por PID distinto; sólo se comparte esta barrera del mismo RUN.
    $raiz = realpath(base_path('storage/framework/testing/agenda-mariadb-639/'.Entorno639::perfil()['RUN']));
    $ruta = realpath($in['barrera'] ?? '');
    if ($raiz === false || $ruta === false || ! str_starts_with(str_replace('\\', '/', $ruta).'/', str_replace('\\', '/', $raiz).'/')) {
        throw new RuntimeException;
    }
    $barrera = new Barrera($ruta);
    $nombre = $in['nombre'];
    CarbonImmutable::setTestNow(CarbonImmutable::parse($in['ahora'], config('app.timezone')));
    $transportes = 0;
    Caso639::transporteSimulado($app, Resultado::tryFrom($in['transporte'] ?? '') ?? Resultado::ACEPTADO,
        function () use (&$transportes): void {
            $transportes++;
        });
    $sesion = Entorno639::verificar(DB::connection())['sesion'];
    $timeout = (int) ($in['timeout'] ?? 5);
    if ($timeout < 1 || $timeout > 10) {
        throw new RuntimeException;
    }
    DB::statement('SET SESSION innodb_lock_wait_timeout = '.$timeout);
    $esperaMutexMs = null;
    if ($in['observar_mutex'] ?? false) {
        $observando = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$observando, $barrera, $nombre): void {
            if (! $observando && preg_match('/\bfrom `kinesiologas`.*\bfor update\b/i', $sql)) {
                $observando = true;
                $barrera->marcar($nombre.'-intentando-mutex');
            }
        });
        DB::listen(function ($e) use (&$esperaMutexMs): void {
            if ($esperaMutexMs === null && preg_match('/\bfrom `kinesiologas`.*\bfor update\b/i', $e->sql)) {
                $esperaMutexMs = (float) $e->time;
            }
        });
    }
    if ($in['pausar'] ?? false) {
        Turno::updating(function () use ($barrera, $nombre): void {
            $barrera->marcar($nombre.'-protegido');
            $barrera->esperar($nombre.'-continuar');
        });
    }
    if ($in['pausar_lectura'] ?? false) {
        $pausado = false;
        DB::listen(function ($e) use (&$pausado, $barrera, $nombre): void {
            if (! $pausado && preg_match('/\bfrom `turnos`/i', $e->sql)) {
                $pausado = true;
                $barrera->marcar($nombre.'-leyendo');
                $barrera->esperar($nombre.'-continuar');
            }
        });
    }
    $barrera->marcar($nombre.'-listo', ['sesion' => $sesion]);
    $barrera->esperar($nombre.'-iniciar');
    $id = (int) ($in['turno'] ?? 0);
    $valor = match ($in['operacion']) {
        'confirmar', 'cancelar' => (function () use ($id, $in): int {
            $t = Turno::findOrFail($id);
            $action = $in['operacion'] === 'confirmar' ? ConfirmarAsistenciaTurnoAction::class : CancelarTurnoAction::class;

            return (int) app($action)->ejecutar($t->paciente->user, $t)->turno->id;
        })(),
        'cancelar_enlace' => (function () use ($app, $in): int {
            $r = Request::create(config('app.url').'/turnos/asistencia/decidir', 'POST', [
                '_csrf_enlace' => $in['csrf'], 'accion' => 'cancelar', 'acepto' => '1',
            ], $in['cookies'], [], ['HTTP_ORIGIN' => config('app.url'), 'HTTP_SEC_FETCH_SITE' => 'same-origin']);

            return $app->make(Kernel::class)->handle($r)->getStatusCode();
        })(),
        'emitir' => app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($id)->enlaceId,
        'registrar' => app(RegistrarRecordatorioTurnoService::class)->registrar($id)->id,
        'despachar' => app(DespacharRecordatorioTurnoService::class)->despachar((int) $in['recordatorio'], $in['identidad'])?->value,
        'generar' => app(GenerarAgendaDiariaService::class)->ejecutar(CarbonImmutable::parse($in['fecha'], config('app.timezone')))->id,
    };
    echo json_encode(['ok' => true, 'valor' => $valor, 'sesion' => $sesion, 'transportes' => $transportes, 'mutex_espera_ms' => $esperaMutexMs]), PHP_EOL;
} catch (Throwable $e) {
    $codigos = [];
    for ($causa = $e; $causa !== null; $causa = $causa->getPrevious()) {
        if ($causa instanceof PDOException || $causa instanceof QueryException) {
            $codigos[] = (int) ($causa->errorInfo[1] ?? 0);
        }
    }
    echo json_encode(['ok' => false, 'clase' => $e::class, 'codigos' => $codigos, 'transportes' => $transportes ?? 0]), PHP_EOL;
    exit(2);
} finally {
    CarbonImmutable::setTestNow();
}
