<?php

namespace Tests\Integration\MySql;

use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumentalMariaDb;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Integration\MySql\Support\MySqlTestCase;

class AdvisoryLocksDocumentalesTest extends MySqlTestCase
{
    #[DataProvider('cierresSql')]
    public function test_session_lock_excludes_other_session_and_survives_sql_commit_or_rollback(string $cierre): void
    {
        $ruta = 'originales/'.$this->escenario;
        $nombre = $this->nombreLock($ruta);
        $coordinador = new CoordinadorBloqueoDocumentalMariaDb(DB::connection());
        $otro = new CoordinadorBloqueoDocumentalMariaDb($this->observador);
        $bloqueo = $coordinador->adquirir($ruta);
        try {
            $this->assertSame((int) $this->preflight['sesion'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
            DB::beginTransaction();
            DB::selectOne('SELECT 1');
            $cierre === 'commit' ? DB::commit() : DB::rollBack();
            $this->assertSame((int) $this->preflight['sesion'], (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
            $this->assertSame((int) $this->preflight['sesion'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
            try {
                $otro->adquirir($ruta);
                $this->fail('La segunda sesión no puede adquirir un lock retenido.');
            } catch (RuntimeException $error) {
                $this->assertSame('No se adquirió el bloqueo documental.', $error->getMessage());
            }
            $this->assertSame(0, (int) $this->observador->selectOne('SELECT RELEASE_LOCK(?) AS liberado', [$nombre])->liberado);
        } finally {
            $bloqueo->liberar();
        }
        $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
        $otro->adquirir($ruta)->liberar();
    }

    public static function cierresSql(): array
    {
        return [['commit'], ['rollback']];
    }

    public function test_release_uses_captured_pdo_even_if_connection_object_changes_its_current_pdo(): void
    {
        $conexion = DB::connection();
        $pdoPropietario = $conexion->getPdo();
        $ruta = 'originales/'.$this->escenario;
        $nombre = $this->nombreLock($ruta);
        $bloqueo = (new CoordinadorBloqueoDocumentalMariaDb($conexion))->adquirir($ruta);
        $liberado = false;
        try {
            $conexion->setPdo($this->observador->getPdo());
            $this->assertNotSame((int) $this->preflight['sesion'], (int) $conexion->selectOne('SELECT CONNECTION_ID() AS id')->id);
            $bloqueo->liberar();
            $liberado = true;
            $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
        } finally {
            $conexion->setPdo($pdoPropietario);
            if (! $liberado) {
                $bloqueo->liberar();
            }
        }
    }

    public function test_physical_worker_session_close_releases_its_named_lock(): void
    {
        $this->iniciar('cierre', $this->paciente(), $this->profesional(), ['operacion' => 'cerrar_con_lock']);
        $this->barrera->esperar('cierre-lock-adquirido');
        $nombre = $this->nombreLock('originales/'.$this->escenario);
        $this->assertSame($this->sesiones['cierre'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
        $this->barrera->marcar('cierre-cerrar');
        $this->assertSame('ok', $this->resultado('cierre')['status']);
        $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
    }
}
