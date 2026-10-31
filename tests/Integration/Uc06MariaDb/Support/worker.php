<?php

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Models\Auditoria;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\Turno;
use App\Models\User;
use App\Services\Turnos\RegistrarIntencionEvaluacionCupoService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Integration\MySql\Support\Barrera;
use Tests\Integration\Uc06MariaDb\Support\EntornoUc06MariaDb;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$sesion = null;
$intentos = 0;
$locks = [];
$etapa = 'guarda';
try {
    // Se rechaza ANTES de leer stdin, arrancar Laravel o abrir cualquier conexión.
    EntornoUc06MariaDb::aplicacion();
    $etapa = 'preflight';
    $preflight = EntornoUc06MariaDb::verificar(DB::connection());
    $sesion = (int) $preflight['sesion'];
    $entrada = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! preg_match('/\A[a-f0-9]{32}\z/', $entrada['escenario'] ?? '')
        || ! preg_match('/\A[a-z]+\z/', $entrada['worker'] ?? '')
        || ! in_array($entrada['operacion'] ?? '', ['confirmar', 'cancelar', 'intencion'], true)) {
        throw new RuntimeException('Entrada del harness inválida.');
    }
    $nombre = $entrada['worker'];
    $barrera = new Barrera(storage_path('framework/testing/uc06-mariadb/'.$entrada['escenario']));
    CarbonImmutable::setTestNow(CarbonImmutable::parse($entrada['ahora'], config('app.timezone')));
    Bus::fake();
    Queue::fake();
    Mail::fake();
    Notification::fake();
    $timeout = (int) ($entrada['lock_timeout'] ?? 15);
    if ($timeout < 1 || $timeout > 15) {
        throw new RuntimeException('Timeout de bloqueo fuera de contrato.');
    }
    DB::statement('SET SESSION innodb_lock_wait_timeout = '.$timeout);
    $actor = User::findOrFail($entrada['actor']);
    $turno = Turno::findOrFail($entrada['turno']);
    Event::listen(TransactionBeginning::class, function (TransactionBeginning $evento) use (&$intentos): void {
        if ($evento->connection->transactionLevel() === 1) {
            $intentos++;
        }
    });
    DB::connection()->beforeExecuting(function ($sql) use (&$locks): void {
        if (preg_match('/from `([a-z_]+)`.*for update/i', $sql, $tabla)) {
            $locks[] = $tabla[1];
        }
    });
    if (isset($entrada['ahora_despues_lock'])) {
        Kinesiologa::retrieved(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse($entrada['ahora_despues_lock'], config('app.timezone'))));
    }
    if ($entrada['pausar'] ?? false) {
        $pausa = function () use ($barrera, $nombre): void {
            $barrera->marcar($nombre.'-protegido');
            $barrera->esperar($nombre.'-continuar');
        };
        $entrada['operacion'] === 'intencion' ? IntencionEvaluacionCupo::created($pausa) : Turno::updating($pausa);
    }
    if (isset($entrada['fallar'])) {
        $fallo = fn () => throw new RuntimeException('Fallo sintético del harness.');
        match ($entrada['fallar']) {
            'auditoria' => Auditoria::created($fallo),
            'intencion' => IntencionEvaluacionCupo::created($fallo),
            default => throw new RuntimeException('Punto de fallo inválido.'),
        };
    }
    $barrera->marcar($nombre.'-listo', ['sesion' => $sesion, 'aislamiento' => $preflight['aislamiento']]);
    $barrera->esperar($nombre.'-iniciar');
    $etapa = 'operacion';
    if ($entrada['operacion'] === 'intencion') {
        $resultado = DB::transaction(fn () => app(RegistrarIntencionEvaluacionCupoService::class)->registrar(Auditoria::findOrFail($entrada['auditoria'])), attempts: 1);
        $salida = ['status' => 'ok', 'intencion_id' => $resultado->id];
    } else {
        $resultado = $entrada['operacion'] === 'cancelar'
            ? app(CancelarTurnoAction::class)->ejecutar($actor, $turno)
            : app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($actor, $turno);
        $salida = ['status' => 'ok', 'turno_id' => $resultado->turno->id, 'estado' => $resultado->turno->estado->value, 'esReplay' => $resultado->esReplay];
    }
    Bus::assertNothingDispatched();
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
} catch (Throwable $error) {
    $codigos = [];
    for ($causa = $error; $causa !== null; $causa = $causa->getPrevious()) {
        if ($causa instanceof QueryException || $causa instanceof PDOException) {
            $codigos[] = (int) ($causa->errorInfo[1] ?? 0);
        }
    }
    $salida = ['status' => 'error', 'excepcion' => $error::class, 'codigos_sql' => $codigos];
} finally {
    CarbonImmutable::setTestNow();
}
// No mensajes de excepciones/SQL, payloads, credenciales ni datos clínicos.
echo json_encode($salida + ['sesion' => $sesion, 'intentos' => $intentos, 'locks' => $locks, 'etapa' => $etapa], JSON_THROW_ON_ERROR), PHP_EOL;
exit($salida['status'] === 'ok' ? 0 : 2);
