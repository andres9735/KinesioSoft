<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Integration\MySql\Support\Barrera;
use Tests\Integration\Uc06MariaDb\Support\ProcesosUc06;

class ProcesosUc06Test extends TestCase
{
    private string $directorio;

    private ProcesosUc06 $workers;

    protected function setUp(): void
    {
        $this->directorio = sys_get_temp_dir().'/ks-uc06-protocolo-'.bin2hex(random_bytes(16));
        $this->workers = new ProcesosUc06(new Barrera($this->directorio));
    }

    protected function tearDown(): void
    {
        $this->workers->detener();
        foreach (['uno-listo.json', 'uno-continuar.json', 'dos-listo.json', 'dos-continuar.json'] as $archivo) {
            if (is_file($this->directorio.'/'.$archivo)) {
                unlink($this->directorio.'/'.$archivo);
            }
        }
        rmdir($this->directorio);
    }

    private function iniciar(string $nombre, string $modo = 'barrera'): void
    {
        $this->workers->iniciar($nombre, dirname(__DIR__).'/Fixtures/uc06-worker-protocolo.php', [
            'directorio' => $this->directorio, 'modo' => $modo,
        ], timeout: 5);
    }

    public function test_two_independent_php_processes_wait_for_explicit_barriers_before_completing(): void
    {
        $this->iniciar('uno');
        $this->iniciar('dos');
        $uno = $this->workers->esperar('uno', 'listo');
        $dos = $this->workers->esperar('dos', 'listo');
        $this->assertNotSame(getmypid(), $uno['pid']);
        $this->assertNotSame($uno['pid'], $dos['pid']);
        $this->assertTrue($this->workers->activo('uno'));
        $this->assertTrue($this->workers->activo('dos'));
        foreach (['uno', 'dos'] as $nombre) {
            $this->workers->barrera->marcar($nombre.'-continuar');
            $this->assertSame('ok', $this->workers->resultado($nombre)['status']);
        }
    }

    public function test_child_crash_cannot_be_misreported_as_a_successful_barrier(): void
    {
        $this->iniciar('uno', 'terminar');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sin publicar');
        $this->workers->esperar('uno', 'listo', 1);
    }

    public function test_missing_event_times_out_and_waiting_child_is_stopped(): void
    {
        $this->iniciar('uno');
        $this->workers->esperar('uno', 'listo');
        try {
            $this->workers->esperar('uno', 'ausente', 0.03);
            $this->fail('Una barrera ausente debe fallar.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Timeout finito', $error->getMessage());
        } finally {
            $this->workers->detener();
        }
        $this->assertFalse($this->workers->activo('uno'));
    }
}
