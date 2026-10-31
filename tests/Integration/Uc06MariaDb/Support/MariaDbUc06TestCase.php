<?php

namespace Tests\Integration\Uc06MariaDb\Support;

use App\Models\Auditoria;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Integration\MySql\Support\Barrera;
use Tests\Support\FixturesAutogestionTurnos;
use Tests\TestCase;
use Throwable;

abstract class MariaDbUc06TestCase extends TestCase
{
    protected FixturesAutogestionTurnos $fixtures;

    protected ProcesosUc06 $workers;

    protected Connection $observador;

    protected CarbonImmutable $ahora;

    protected int $sesion;

    private int $sesionObservador;

    protected array $sesiones = [];

    private string $escenario;

    private ?Application $aplicacionCaso = null;

    private bool $casoFinalizado = false;

    public function createApplication()
    {
        $anterior = Container::getInstance();
        try {
            return EntornoUc06MariaDb::aplicacion();
        } finally {
            // También conservar la aplicación propia si falla durante su bootstrap.
            $actual = Container::getInstance();
            if ($actual !== $anterior && $actual instanceof Application) {
                $this->aplicacionCaso = $actual;
            }
        }
    }

    protected function setUp(): void
    {
        if (getenv('UC06_MYSQL_ETAPA_B') !== EntornoUc06MariaDb::MARCADOR) {
            $this->markTestSkipped('MariaDB real pendiente de autorización específica de etapa B; no hubo conexión.');
        }
        try {
            parent::setUp();
            $preflight = EntornoUc06MariaDb::verificar(DB::connection(), exigirVacia: true);
            $this->assertSame(0, DB::transactionLevel());
            $this->sesion = (int) $preflight['sesion'];
            DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
            $this->observador = EntornoUc06MariaDb::independiente('uc06_observador');
            $this->sesionObservador = (int) $this->observador->selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($this->sesion, $this->sesionObservador);
            $this->ahora = CarbonImmutable::parse('2026-10-30 10:00:00', config('app.timezone'));
            CarbonImmutable::setTestNow($this->ahora);
            $this->fixtures = new FixturesAutogestionTurnos;
            $this->escenario = bin2hex(random_bytes(16));
            $this->workers = new ProcesosUc06(new Barrera(storage_path('framework/testing/uc06-mariadb/'.$this->escenario)));
        } catch (Throwable $error) {
            // PHPUnit no garantiza tearDown() cuando setUp() termina con excepción.
            $this->finalizarCaso([['fase' => 'setUp', 'error' => $error]]);
        }
    }

    protected function iniciar(string $nombre, string $operacion, Turno $turno, array $opciones = []): void
    {
        $this->workers->iniciar($nombre, __DIR__.'/worker.php', $opciones + [
            'escenario' => $this->escenario, 'operacion' => $operacion,
            'turno' => $turno->id, 'actor' => $turno->paciente->user_id, 'ahora' => $this->ahora->toIso8601String(),
        ]);
        $listo = $this->workers->esperar($nombre, 'listo');
        $this->assertSame('REPEATABLE-READ', $listo['aislamiento']);
        $this->assertNotContains((int) $listo['sesion'], [$this->sesion, $this->sesionObservador, ...$this->sesiones]);
        $this->sesiones[$nombre] = (int) $listo['sesion'];
        $this->workers->barrera->marcar($nombre.'-iniciar');
    }

    protected function esperarBloqueo(string $worker, int $propietario): void
    {
        $solicitante = $this->sesiones[$worker];
        $espera = Barrera::hasta(function () use ($worker, $solicitante, $propietario) {
            if (! $this->workers->activo($worker)) {
                throw new RuntimeException('El worker terminó sin observar espera InnoDB real.');
            }

            return $this->observador->selectOne(
                'SELECT r.trx_mysql_thread_id AS solicitante, b.trx_mysql_thread_id AS propietario FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=? AND r.trx_state=?',
                [$solicitante, $propietario, 'LOCK WAIT'],
            );
            // Más de 100 ms permite renovar la caché informativa de MariaDB 10.4.
        }, 8, 150000);
        $this->assertSame($solicitante, (int) $espera->solicitante);
        $this->assertSame($propietario, (int) $espera->propietario);
    }

    protected function resultado(string $worker, bool $esperarLocks = true): array
    {
        $resultado = $this->workers->resultado($worker);
        $this->assertSame($this->sesiones[$worker], $resultado['sesion']);
        $this->assertSame(1, $resultado['intentos'], 'Sin reintentos transaccionales automáticos.');
        if ($esperarLocks) {
            $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], array_slice($resultado['locks'], 0, 3));
        }

        return $resultado;
    }

    protected function conteos(int $confirmaciones, int $cancelaciones, int $intenciones): void
    {
        $this->assertSame($confirmaciones, Auditoria::where('evento', 'turno_asistencia_confirmada')->count());
        $this->assertSame($cancelaciones, Auditoria::where('evento', 'turno_cancelado')->count());
        $this->assertSame($intenciones, IntencionEvaluacionCupo::count());
        foreach (IntencionEvaluacionCupo::all() as $intencion) {
            $this->assertSame('turno_cancelado', $intencion->cancelacion->evento);
            $this->assertSame($intencion->turno_id, (int) $intencion->cancelacion->auditable_id);
            $this->assertSame('CANCELADO', $intencion->turno->estado->value);
        }
    }

    protected function tearDown(): void
    {
        $this->finalizarCaso();
    }

    private function finalizarCaso(array $errores = []): void
    {
        // PHPUnit puede invocar tearDown() después de un setUp() fallido ya limpiado.
        if ($this->casoFinalizado) {
            return;
        }
        $this->casoFinalizado = true;
        // Retener el gestor propio antes de que Laravel vacíe su contenedor.
        $gestor = $this->aplicacionCaso?->bound('db') ? $this->aplicacionCaso['db'] : null;
        try {
            if (isset($this->workers)) {
                $this->workers->detener();
            }
            foreach ($gestor?->getConnections() ?? [] as $conexion) {
                while ($conexion->transactionLevel() > 0) {
                    $conexion->rollBack();
                }
            }
            if (isset($this->observador)) {
                foreach ($this->sesiones as $id) {
                    Barrera::hasta(fn () => (int) $this->observador->selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE ID=?', [$id])->n === 0, 3, 150000);
                    Barrera::hasta(fn () => (int) $this->observador->selectOne('SELECT COUNT(*) AS n FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=?', [$id])->n === 0, 3, 150000);
                }
                // Si no se pudo demostrar el cierre de workers, no intentar limpiar filas.
                if (isset($this->fixtures)) {
                    EntornoUc06MariaDb::verificar(DB::connection());
                    $this->fixtures->limpiar();
                    EntornoUc06MariaDb::verificar(DB::connection(), exigirVacia: true, sesionesPermitidas: [$this->sesionObservador]);
                }
            }
            if (isset($this->escenario)) {
                $base = realpath(storage_path('framework/testing/uc06-mariadb'));
                $ruta = realpath(storage_path('framework/testing/uc06-mariadb/'.$this->escenario));
                if ($base !== false && $ruta !== false && ! is_link($ruta)
                    && str_starts_with(strtolower(str_replace('\\', '/', $ruta)), strtolower(str_replace('\\', '/', $base)).'/')) {
                    (new Filesystem)->deleteDirectory($ruta);
                }
            }
        } catch (Throwable $error) {
            $errores[] = ['fase' => 'limpieza', 'error' => $error];
        } finally {
            try {
                CarbonImmutable::setTestNow();
            } catch (Throwable $error) {
                $errores[] = ['fase' => 'reloj', 'error' => $error];
            }
            try {
                parent::tearDown();
            } catch (Throwable $error) {
                $errores[] = ['fase' => 'tearDown Laravel', 'error' => $error];
            } finally {
                // Incluye mysql y uc06_observador, aun si independiente() falló
                // antes de asignar la propiedad. No abrir conexiones ni usar otro gestor.
                foreach ($gestor?->getConnections() ?? [] as $nombre => $conexion) {
                    try {
                        $gestor->purge($nombre);
                    } catch (Throwable $error) {
                        $errores[] = ['fase' => 'desconexión '.$nombre, 'error' => $error];
                    }
                }
                unset($this->observador);
                $this->aplicacionCaso = null;
            }
        }
        if (count($errores) === 1) {
            throw $errores[0]['error'];
        }
        if ($errores !== []) {
            // Mantener todos los Throwable y el primero como causa original.
            throw new class($errores) extends RuntimeException
            {
                public function __construct(public readonly array $errores)
                {
                    $fases = array_map(fn (array $fallo) => $fallo['fase'].': '.$fallo['error']::class, $errores);
                    parent::__construct('UC06: múltiples errores de ciclo de vida: '.implode('; ', $fases), 0, $errores[0]['error']);
                }
            };
        }
    }
}
