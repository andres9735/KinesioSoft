<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Integration\MySql\Support\Barrera;

class BarreraMariaDbTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        $this->directorio = sys_get_temp_dir().'/ks-uc07-barrera-'.bin2hex(random_bytes(16));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directorio)) {
            foreach (['listo.json', 'listo.json.tmp'] as $archivo) {
                if (is_file($this->directorio.'/'.$archivo)) {
                    unlink($this->directorio.'/'.$archivo);
                }
            }
            rmdir($this->directorio);
        }
    }

    public function test_publishes_complete_metadata_for_another_barrier_instance(): void
    {
        $emisor = new Barrera($this->directorio);
        $receptor = new Barrera($this->directorio);
        $this->assertNull($receptor->leer('listo'));
        $emisor->marcar('listo', ['sesion' => 42]);
        $this->assertSame(['sesion' => 42], $receptor->esperar('listo', 0.1));
        $this->assertFileDoesNotExist($this->directorio.'/listo.json.tmp');
    }

    public function test_missing_signal_has_a_finite_timeout(): void
    {
        $barrera = new Barrera($this->directorio);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Timeout finito');
        $barrera->esperar('ausente', 0.02);
    }

    public function test_marker_cannot_escape_its_namespace(): void
    {
        $barrera = new Barrera($this->directorio);
        $this->expectException(RuntimeException::class);
        $barrera->marcar('../ajeno');
    }
}
