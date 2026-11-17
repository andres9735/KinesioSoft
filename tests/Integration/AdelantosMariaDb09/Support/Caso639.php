<?php

namespace Tests\Integration\AdelantosMariaDb09\Support;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Models\Turno;
use App\Services\Turnos\RegistrarRecordatorioTurnoService;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Integration\MySql\Support\Barrera;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

/** Sin migraciones automáticas, transacción envolvente ni limpieza de datos. */
abstract class Caso639 extends TestCase
{
    use CreaPerfilesUc07;

    private static int $numero = 0;

    private array $procesos = [];

    protected Barrera $barrera;

    private string $escenario;

    public function createApplication()
    {
        return Entorno639::aplicacion();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Entorno639::origen(DB::connection());
        $this->assertSame(0, DB::transactionLevel());
        $this->withoutVite();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone'))->addDays(40 * ++self::$numero));
        $this->escenario = bin2hex(random_bytes(16));
        $this->barrera = new Barrera(storage_path('barreras/'.$this->escenario));
        config(['recordatorios.horario_inicio' => null, 'recordatorios.horario_fin' => null]);
        self::transporteSimulado($this->app, Resultado::ACEPTADO);
    }

    public static function transporteSimulado($app, Resultado $resultado, ?Closure $alTransportar = null): void
    {
        $app->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->andReturnUsing(function (Closure $contenido) use ($resultado, $alTransportar): Resultado {
                if (DB::transactionLevel() !== 0) {
                    throw new RuntimeException('639: transporte dentro de una transacción.');
                }
                $contenido()->render(); // Construcción real, transporte deliberadamente simulado.
                $alTransportar?->__invoke();

                return $resultado;
            })->getMock());
    }

    protected function turno(int $horas = 15, string $estado = 'PENDIENTE'): Turno
    {
        return Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => $estado,
            'inicio_at' => CarbonImmutable::now()->addHours($horas),
            'fin_at' => CarbonImmutable::now()->addHours($horas)->addMinutes(30),
            'motivo_solicitud' => 'CLINICO-SINTETICO-639-NO-EXPONER',
        ])->fresh();
    }

    protected function recordatorio(Turno $turno): int
    {
        return (int) app(RegistrarRecordatorioTurnoService::class)->registrar($turno->id)->id;
    }

    protected function interno(): void
    {
        Auth::forgetGuards();
        $this->app->instance('request', Request::create('/'));
    }

    protected function iniciar(string $nombre, string $operacion, array $datos = []): void
    {
        Entorno639::origen(DB::connection());
        $env = array_fill_keys(array_keys(getenv()), false);
        foreach (getenv() as $key => $value) {
            if (str_starts_with($key, 'AAT09_') || in_array(strtoupper($key), ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'TEMP', 'TMP'], true)) {
                $env[$key] = $value;
            }
        }
        $args = [PHP_BINARY, '-n', '-d', 'extension_dir='.ini_get('extension_dir')];
        foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'openssl'] as $ext) {
            array_push($args, '-d', 'extension='.$ext);
        }
        array_push($args, '-d', 'zend.exception_ignore_args=1', dirname(__DIR__).'/worker.php');
        $p = new Process($args, base_path(), $env, json_encode($datos + [
            'nombre' => $nombre, 'operacion' => $operacion, 'barrera' => storage_path('barreras/'.$this->escenario),
            'ahora' => CarbonImmutable::now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR), 25);
        $this->procesos[$nombre] = $p;
        $p->start();
        $this->barrera->esperar($nombre.'-listo');
        $this->barrera->marcar($nombre.'-iniciar');
    }

    protected function resultado(string $nombre): array
    {
        $p = $this->procesos[$nombre];
        $p->wait();
        // Nunca emitir stdout/stderr ni mensajes arbitrarios de excepciones.
        $salida = json_decode(trim($p->getOutput()), true);
        $this->assertTrue(in_array($p->getExitCode(), [0, 2], true) && is_array($salida), '639: worker fuera de protocolo.');

        return $salida;
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->procesos as $p) {
                if ($p->isRunning()) {
                    $p->stop(0.5);
                }
            }
            if (isset($this->app) && $this->app->bound('db')) {
                foreach ($this->app['db']->getConnections() as $nombre => $c) {
                    while ($c->transactionLevel() > 0) {
                        $c->rollBack();
                    }
                    $this->app['db']->purge($nombre);
                }
            }
            CarbonImmutable::setTestNow();
        } finally {
            parent::tearDown();
        }
        // Conservar fixtures, revisiones, barreras y evidencia: no DELETE/TRUNCATE/DROP.
    }
}
