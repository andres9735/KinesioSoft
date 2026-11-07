<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Mail\RecordatorioTurnoMail;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Tests\Support\StreamSmtpRecordatorio;
use Tests\TestCase;

/** Protocolo SMTP simulado en memoria: no abre sockets ni acredita Mailpit real. */
class TransportarRecordatorioTurnoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1025,
            'url' => null, 'scheme' => null, 'username' => null, 'password' => null],
            'mail.from.address' => 'consultorio@example.test', 'mail.from.name' => 'Consultorio']);
    }

    private function correo(): RecordatorioTurnoMail
    {
        return new RecordatorioTurnoMail(7, ['destinatario' => 'paciente@example.test', 'tipo' => 'Primera consulta',
            'fecha' => '07/11/2026', 'horario' => '10:00–10:30', 'profesional' => 'Profesional Ejemplo',
            'estado' => 'Pendiente', 'confirmar' => true, 'cancelar' => true,
            'url' => 'https://consultorio.example.test/turnos/asistencia?token='.str_repeat('a', 64)]);
    }

    private function adaptador(AbstractStream $stream): TransportarRecordatorioTurnoService
    {
        return new class($stream) extends TransportarRecordatorioTurnoService
        {
            public function __construct(private AbstractStream $stream) {}

            protected function crearTransporte(string $host, int $puerto): SmtpTransport
            {
                return new SmtpTransport($this->stream);
            }
        };
    }

    public function test_smtp_acepta_destinatario_y_mailable_sin_eventos_ni_entrega_inferida(): void
    {
        Event::fake([MessageSending::class, MessageSent::class]);
        $stream = new StreamSmtpRecordatorio;
        $resultado = $this->adaptador($stream)->enviar(fn () => $this->correo());
        $this->assertSame(Resultado::ACEPTADO, $resultado);
        $this->assertContains("RCPT TO:<paciente@example.test>\r\n", $stream->escrituras);
        $contenido = quoted_printable_decode(implode('', $stream->escrituras));
        foreach (['Primera consulta', '07/11/2026', 'Profesional Ejemplo', 'Confirmar asistencia', 'Cancelar turno'] as $texto) {
            $this->assertTrue(str_contains($contenido, $texto), 'Falta un dato administrativo aprobado.');
        }
        $this->assertTrue(str_contains($contenido, '?token='.str_repeat('a', 64)), 'El SMTP debe recibir el enlace autorizado.');
        $this->assertFalse(str_contains($contenido, 'CLINICO-NO-EXPONER'));
        Event::assertNotDispatched(MessageSending::class);
        Event::assertNotDispatched(MessageSent::class);
        $this->assertTrue((bool) preg_match('/^Subject: (.+(?:\r\n[ \t].+)*)/m', $contenido, $asunto));
        $this->assertSame('Recordatorio de turno — KinesioSoft', trim(mb_decode_mimeheader($asunto[1])));
    }

    public function test_fallo_de_conexion_es_conocido_y_no_recupera_material(): void
    {
        $stream = new StreamSmtpRecordatorio;
        $stream->fallaInicio = true;
        $llamado = false;
        $this->assertSame(Resultado::FALLO_RECUPERABLE, $this->adaptador($stream)->enviar(function () use (&$llamado) {
            $llamado = true;

            return $this->correo();
        }));
        $this->assertFalse($llamado);
        $this->assertSame([], $stream->escrituras);
    }

    public function test_perdida_de_respuesta_final_no_se_declara_fallo_confirmado(): void
    {
        $stream = new StreamSmtpRecordatorio;
        $stream->respuestas[6] = '';
        $this->assertSame(Resultado::INCIERTO, $this->adaptador($stream)->enviar(fn () => $this->correo()));
        $this->assertTrue(in_array("DATA\r\n", $stream->escrituras, true));
    }

    public function test_error_de_quit_no_borra_aceptacion_conocida(): void
    {
        $stream = new StreamSmtpRecordatorio;
        $stream->respuestas[7] = '';
        $this->assertSame(Resultado::ACEPTADO, $this->adaptador($stream)->enviar(fn () => $this->correo()));
    }

    #[DataProvider('rechazosSmtp')]
    public function test_respuesta_negativa_explicita_es_fallo_conocido(int $paso, int $codigo, Resultado $resultado): void
    {
        $stream = new StreamSmtpRecordatorio;
        $stream->respuestas[$paso] = $codigo." Rechazo sintético\r\n";
        $this->assertSame($resultado, $this->adaptador($stream)->enviar(fn () => $this->correo()));
    }

    public static function rechazosSmtp(): array
    {
        return [[4, 450, Resultado::FALLO_RECUPERABLE], [6, 450, Resultado::FALLO_RECUPERABLE],
            [4, 550, Resultado::FALLO_DEFINITIVO], [6, 550, Resultado::FALLO_DEFINITIVO]];
    }

    public function test_fallo_de_preparacion_no_transmite_data_y_no_filtra_excepcion(): void
    {
        $stream = new StreamSmtpRecordatorio;
        $resultado = $this->adaptador($stream)->enviar(fn () => throw new RuntimeException('token=SECRETO CLINICO'));
        $this->assertSame(Resultado::FALLO_DEFINITIVO, $resultado);
        $this->assertNotContains("DATA\r\n", $stream->escrituras);
    }

    #[DataProvider('configuraciones')]
    public function test_rechaza_configuracion_insegura_sin_abrir_transporte(string $clave, mixed $valor): void
    {
        config(['mail.mailers.smtp.'.$clave => $valor]);
        $stream = new StreamSmtpRecordatorio;
        $this->assertSame(Resultado::FALLO_DEFINITIVO, $this->adaptador($stream)->enviar(fn () => $this->correo()));
        $this->assertSame([], $stream->escrituras);
    }

    public static function configuraciones(): array
    {
        return [['transport', 'log'], ['host', 'smtp.external.example'], ['url', 'smtp://external.example'],
            ['port', 0], ['scheme', 'smtps'], ['username', 'credencial-sintetica']];
    }

    public function test_transaccion_abierta_no_autoriza_smtp(): void
    {
        DB::beginTransaction();
        try {
            $stream = new StreamSmtpRecordatorio;
            $this->assertSame(Resultado::FALLO_DEFINITIVO, $this->adaptador($stream)->enviar(fn () => $this->correo()));
            $this->assertSame([], $stream->escrituras);
        } finally {
            DB::rollBack();
        }
    }
}
