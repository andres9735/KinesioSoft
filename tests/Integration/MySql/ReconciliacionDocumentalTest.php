<?php

namespace Tests\Integration\MySql;

use App\Models\DocumentoClinico;
use App\Services\Turnos\ReconciliadorDocumentosClinicosService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\MySql\Support\MySqlTestCase;

class ReconciliacionDocumentalTest extends MySqlTestCase
{
    #[DataProvider('cierresEscritor')]
    public function test_active_writer_protects_old_file_then_commit_preserves_or_rollback_leaves_cleanable_orphan(bool $rollback): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
        try {
            $this->iniciar('escritor', $this->paciente(), $this->profesional(), [
                'archivos' => 1, 'pausar' => true, 'pausar_final' => true,
                'fallar_documento' => $rollback, 'fallar_compensacion' => $rollback,
            ]);
            $this->barrera->esperar('escritor-antes-insertar');
            $this->assertTrue($this->procesos['escritor']->isRunning());
            $this->assertSame(0, DocumentoClinico::count());
            $disco = Storage::disk('documentos_clinicos');
            $rutas = $disco->allFiles('originales');
            $this->assertCount(1, $rutas);
            $ruta = $rutas[0];
            $nombre = $this->nombreLock($ruta);
            $this->assertSame($this->sesiones['escritor'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
            $this->assertTrue(touch($disco->path($ruta), CarbonImmutable::getTestNow()->subHours(25)->getTimestamp()));

            // La sesión padre del reconciliador es independiente del escritor y del observador.
            $sesionReconciliador = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($this->sesiones['escritor'], $sesionReconciliador);
            config(['documentos_clinicos.lock_timeout_segundos' => 5]);
            $servicio = app(ReconciliadorDocumentosClinicosService::class);
            $enVuelo = $servicio->reconciliar(true);
            $this->assertTrue($enVuelo->exitoso());
            $this->assertSame(1, $enVuelo->conteos['en_uso_lock']);
            $this->assertSame(0, $enVuelo->conteos['recientes']);
            $this->assertSame(0, $enVuelo->conteos['candidatos']);
            $this->assertSame(0, $enVuelo->conteos['eliminados']);
            $disco->assertExists($ruta);
            $this->assertSame($this->sesiones['escritor'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);

            $this->barrera->marcar('escritor-continuar');
            $fin = $this->barrera->esperar('escritor-finalizada');
            $this->assertSame($rollback ? 'error' : 'ok', $fin['status']);
            // El worker aún vive: el lock se liberó explícitamente, no por cierre del PDO.
            $this->assertTrue($this->procesos['escritor']->isRunning());
            $this->assertSame(1, (int) $this->observador->selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE ID = ?', [$this->sesiones['escritor']])->n);
            $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
            $disco->assertExists($ruta);

            $posterior = $servicio->reconciliar(true);
            $this->assertTrue($posterior->exitoso());
            $this->assertSame($rollback ? 0 : 1, $posterior->conteos['referenciados']);
            $this->assertSame($rollback ? 1 : 0, $posterior->conteos['eliminados']);
            $this->assertSame(! $rollback, $disco->exists($ruta));
            $this->assertSame($rollback ? 0 : 1, DocumentoClinico::where('ruta_storage', $ruta)->count());
            $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
            $this->barrera->marcar('escritor-salir');
            $this->resultado('escritor');
            $this->conteos($rollback ? 0 : 1, $rollback ? 0 : 1);
            $this->diagnostico = ['reconciliador_sesion' => $sesionReconciliador, 'en_vuelo' => $enVuelo->conteos, 'posterior' => $posterior->conteos];
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public static function cierresEscritor(): array
    {
        return ['commit' => [false], 'rollback con residuo por compensacion fallida' => [true]];
    }
}
