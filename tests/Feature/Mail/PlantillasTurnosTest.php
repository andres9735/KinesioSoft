<?php

namespace Tests\Feature\Mail;

use App\Mail\ConfirmacionAdelantoMail;
use App\Mail\OfertaAdelantoMail;
use App\Mail\RecordatorioTurnoMail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlantillasTurnosTest extends TestCase
{
    private const URL = 'https://consultorio.example.test/turnos/adelanto?token=credencial-ficticia';

    private function datos(): array
    {
        return ['paciente' => 'Lucía <script>privado</script>', 'destinatario' => 'prueba@example.test',
            'profesional' => 'Silvia Duarte', 'estado' => 'Pendiente', 'zona' => 'America/Argentina/Buenos_Aires'];
    }

    #[DataProvider('acciones')]
    public function test_recordatorio_respeta_acciones_y_escapa_el_nombre(bool $confirmar, bool $cancelar): void
    {
        $html = (new RecordatorioTurnoMail(1, $this->datos() + [
            'tipo' => 'Primera consulta', 'fecha' => '19/11/2026', 'horario' => '08:00–08:45',
            'confirmar' => $confirmar, 'cancelar' => $cancelar, 'url' => self::URL,
        ]))->render();
        $this->assertStringContainsString('Hola, Lucía &lt;script&gt;privado&lt;/script&gt;.', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>Primera consulta</strong>', $html);
        $this->assertStringContainsString('<strong>08:00</strong>', $html);
        $this->assertStringNotContainsString('08:45', $html);
        $this->assertSame($confirmar, str_contains($html, '>Confirmar asistencia<'));
        $this->assertSame($cancelar, str_contains($html, '>Cancelar turno<'));
        $this->assertSame($confirmar || $cancelar, str_contains($html, self::URL));
        $this->assertStringNotContainsString('<form', $html);
    }

    public static function acciones(): array
    {
        return [[true, true], [true, false], [false, true], [false, false]];
    }

    public function test_oferta_separa_horarios_y_convierte_solo_la_presentacion_del_vencimiento(): void
    {
        $html = (new OfertaAdelantoMail(2, $this->datos() + [
            'actual' => '23/11/2026 08:00:00–08:45:00', 'ofrecido' => '19/11/2026 09:15:00–10:00:00',
            'vencimiento' => '19/11/2026 00:57:59.123456 +02:00', 'url' => self::URL,
        ]))->render();
        foreach (['Oferta de adelanto de turno', 'Horario actual', 'Lunes 23/11/2026', 'Horario ofrecido',
            'Jueves 19/11/2026', '<strong>09:15</strong>', '18/11/2026 a las 19:57 hs.', 'hora argentina',
            'Tu turno conserva su horario y estado', "Georgia,'Times New Roman',serif", self::URL] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['08:45', '09:15:00', '10:00:00', '123456', '+02:00', '<script>'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }

    #[DataProvider('estados')]
    public function test_confirmacion_conserva_el_estado_y_enlaza_a_la_ruta_existente(string $estado): void
    {
        config(['app.url' => 'https://consultorio.example.test']);
        $datos = array_merge($this->datos(), ['estado' => $estado, 'horario' => '19/11/2026 08:00:00–08:45:00']);
        $html = (new ConfirmacionAdelantoMail(3, $datos))->render();
        foreach (['Nueva fecha', 'Jueves 19/11/2026', 'Nueva hora', '<strong>08:00</strong>', $estado,
            'href="https://consultorio.example.test/turnos/mis-turnos"', '>Ver mis turnos<'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('08:45', $html);
        $this->assertStringNotContainsString('?token=', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public static function estados(): array
    {
        return [['Pendiente'], ['Confirmado']];
    }
}
