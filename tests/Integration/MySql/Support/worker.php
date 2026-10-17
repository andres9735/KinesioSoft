<?php

use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Models\DocumentoClinico;
use App\Models\Turno;
use App\Models\User;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Integration\MySql\Support\Barrera;
use Tests\Integration\MySql\Support\EntornoMariaDb;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$temporales = [];
$intentos = 0;
$sesion = null;
$barrera = null;
try {
    $entrada = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    EntornoMariaDb::aplicacion();
    EntornoMariaDb::configurarStorage($entrada['escenario']);
    $preflight = EntornoMariaDb::verificar(DB::connection());
    $sesion = (int) $preflight['sesion'];
    $barrera = new Barrera(storage_path('framework/testing/uc07-mysql/'.$entrada['escenario'].'/barreras'));
    $nombre = $entrada['worker'];
    if (! preg_match('/\A[a-z]+\z/', $nombre)) {
        throw new RuntimeException('Worker inválido.');
    }
    $barrera->marcar($nombre.'-listo', ['sesion' => $sesion, 'aislamiento' => $preflight['aislamiento']]);
    $barrera->esperar($nombre.'-iniciar');
    if (($entrada['operacion'] ?? '') === 'cerrar_con_lock') {
        $bloqueo = app(CoordinadorBloqueoDocumental::class)->adquirir('originales/'.$entrada['escenario']);
        $barrera->marcar($nombre.'-lock-adquirido');
        $barrera->esperar($nombre.'-cerrar');
        echo json_encode(['status' => 'ok', 'sesion' => $sesion, 'intentos' => 0], JSON_THROW_ON_ERROR), PHP_EOL;
        // Fin físico del proceso/sesión, deliberadamente sin RELEASE_LOCK para esta prueba.
        exit(0);
    }
    DB::statement('SET SESSION innodb_lock_wait_timeout = '.(int) ($entrada['lock_timeout'] ?? 15));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
    Event::listen(TransactionBeginning::class, function () use (&$intentos): void {
        $intentos++;
    });
    if ($entrada['snapshot'] ?? false) {
        $capturado = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$capturado, $barrera, $nombre): void {
            if (! $capturado && str_contains($sql, 'from `kinesiologas`') && str_contains($sql, 'for update')) {
                $capturado = true;
                // Sólo harness: congelar un read view anterior al mutex para exigir current reads posteriores.
                foreach (['turnos', 'users', 'franjas_horarias_agenda', 'bloqueos_agenda', 'model_has_roles', 'matriculas_profesionales_kinesiologia', 'kinesiologa_especialidad'] as $tabla) {
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
    if ($entrada['fallar_documento'] ?? false) {
        DocumentoClinico::creating(fn () => throw new RuntimeException('Falla documental controlada del harness.'));
    }
    $archivos = [];
    for ($i = 0; $i < ($entrada['archivos'] ?? 0); $i++) {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-mysql-');
        $temporales[] = $ruta;
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $archivos[] = new UploadedFile($ruta, 'fixture.pdf', 'application/octet-stream', UPLOAD_ERR_OK, true);
    }
    $resultado = app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
        User::findOrFail($entrada['actor']), $entrada['uuid'], $entrada['kinesiologa'],
        CarbonImmutable::parse('2026-10-05 10:00:00', config('app.timezone')), 'Motivo del fixture', $archivos,
    );
    $salida = ['status' => 'ok', 'turno_id' => $resultado->turno->id, 'esReplay' => $resultado->esReplay];
} catch (Throwable $error) {
    $codigos = [];
    for ($causa = $error; $causa !== null; $causa = $causa->getPrevious()) {
        if ($causa instanceof QueryException || $causa instanceof PDOException) {
            $codigos[] = (int) ($causa->errorInfo[1] ?? 0);
        }
    }
    $salida = ['status' => 'error', 'excepcion' => $error::class, 'codigos_sql' => $codigos];
    if ($error instanceof QueryException && preg_match('/\b(?:from|into)\s+`?([a-z_]+)`?/i', $error->getSql(), $tabla)) {
        $salida['tabla_error'] = $tabla[1];
    }
} finally {
    foreach ($temporales as $ruta) {
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }
}
$salida += ['sesion' => $sesion, 'intentos' => $intentos, 'finalizado_at' => microtime(true)];
if (($entrada['pausar_final'] ?? false) && $barrera !== null) {
    // La sesión sigue viva: permite distinguir RELEASE_LOCK explícito de liberación por exit.
    $barrera->marcar($nombre.'-finalizada', $salida);
    $barrera->esperar($nombre.'-salir');
}
// Sólo identificadores y diagnóstico técnico; nunca mensajes SQL, payload ni rutas clínicas.
echo json_encode($salida, JSON_THROW_ON_ERROR), PHP_EOL;
exit($salida['status'] === 'ok' ? 0 : 2);
