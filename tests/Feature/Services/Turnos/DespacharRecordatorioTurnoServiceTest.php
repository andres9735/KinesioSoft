<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Jobs\DespacharRecordatorioTurnoJob;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntentoRecordatorioTurno;
use App\Models\RecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Turnos\DespacharRecordatorioTurnoService;
use App\Services\Turnos\PrepararContenidoRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\Support\CreaPerfilesUc07;
use Tests\Support\StreamSmtpRecordatorio;
use Tests\TestCase;

class DespacharRecordatorioTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;

    private Turno $turno;

    private int $id;

    private string $identidad;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite en memoria sin PDO MySQL.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA',
            'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        $this->id = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar()[0];
        $this->identidad = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function transporte(Resultado $resultado, ?Closure $antes = null): void
    {
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andReturnUsing(function (Closure $preparar) use ($resultado, $antes): Resultado {
                $this->assertSame(0, DB::transactionLevel());
                $this->assertSame(Resultado::INCIERTO, IntentoRecordatorioTurno::sole()->resultado);
                $antes?->__invoke();
                $correo = $preparar();
                $html = $correo->render();
                $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $html);
                $this->assertStringContainsString('Primera consulta', $html);
                $this->assertSame($this->turno->paciente->user->email, $correo->to[0]['address']);

                return $resultado;
            })->getMock());
    }

    private function ejecutar(): Resultado
    {
        return app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $this->identidad);
    }

    public function test_recorrido_completo_sobre_mailable_y_smtp_simulado(): void
    {
        config(['mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1025,
            'url' => null, 'scheme' => null, 'username' => null, 'password' => null],
            'mail.from.address' => 'consultorio@example.test', 'mail.from.name' => 'Consultorio']);
        $stream = new StreamSmtpRecordatorio;
        $this->instance(TransportarRecordatorioTurnoService::class, new class($stream) extends TransportarRecordatorioTurnoService
        {
            public function __construct(private StreamSmtpRecordatorio $stream) {}

            protected function crearTransporte(string $host, int $puerto): SmtpTransport
            {
                return new SmtpTransport($this->stream);
            }
        });
        $antes = $this->turno->fresh()->getAttributes();
        $this->assertSame(Resultado::ACEPTADO, $this->ejecutar());
        $cuerpo = quoted_printable_decode(implode('', $stream->escrituras));
        $this->assertTrue((bool) preg_match('/\?token=([a-f0-9]{64})/', $cuerpo, $credencial), 'Falta la credencial de entrega.');
        $this->assertTrue(EnlaceAsistenciaTurno::sole()->coincideConToken($credencial[1]));
        $this->assertContains('RCPT TO:<'.$this->turno->paciente->user->email.">\r\n", $stream->escrituras);
        $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $cuerpo);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->assertNull(IntentoRecordatorioTurno::sole()->evidencia_entrega);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertStringNotContainsString($credencial[1], Auditoria::all()->toJson());
    }

    #[DataProvider('resultados')]
    public function test_resultados_conocidos_e_inciertos_sin_cambiar_turno(Resultado $resultado, string $estado, string $sobre): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $this->transporte($resultado);
        $this->assertSame($resultado, $this->ejecutar());
        $this->assertSame($resultado, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertSame($estado, RecordatorioTurno::sole()->estado->value);
        $this->assertSame($sobre, SobreEntregaEnlace::sole()->estado);
        $this->assertSame($sobre === 'CERRADO', SobreEntregaEnlace::sole()->material_cifrado === null);
        $this->assertNull(IntentoRecordatorioTurno::sole()->evidencia_entrega);
        if ($resultado === Resultado::FALLO_RECUPERABLE) {
            $this->assertTrue(SobreEntregaEnlace::sole()->vence_at->equalTo(IntentoRecordatorioTurno::sole()->resuelto_at->addHour()));
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $auditorias = Auditoria::count();
        $this->assertSame($resultado, $this->ejecutar());
        $this->assertSame($auditorias, Auditoria::count());
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertStringNotContainsString('?token=', Auditoria::all()->toJson());
        $this->assertStringNotContainsString('?token=', DB::table('recordatorios_turno')->get()->toJson());
        $this->assertStringNotContainsString('?token=', DB::table('intentos_recordatorio_turno')->get()->toJson());
    }

    public static function resultados(): array
    {
        return [[Resultado::ACEPTADO, 'PROCESADO', 'CERRADO'], [Resultado::INCIERTO, 'INCIERTO', 'INCIERTO'],
            [Resultado::FALLO_RECUPERABLE, 'FALLIDO', 'DISPONIBLE'], [Resultado::FALLO_DEFINITIVO, 'FALLIDO', 'CERRADO']];
    }

    public function test_aviso_confirmado_menor_a_doce_horas_no_emite_enlace(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CONFIRMADO']);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(18));
        $this->transporte(Resultado::ACEPTADO);
        $this->assertSame(Resultado::ACEPTADO, $this->ejecutar());
        $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
        $this->assertDatabaseCount('sobres_entrega_enlace', 0);
    }

    #[DataProvider('invalidaciones')]
    public function test_rechaza_cambio_incompatible_antes_del_despacho(string $cambio): void
    {
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($this->id);
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldNotReceive('enviar')->getMock());
        $this->cambiar($cambio);
        $this->expectException(RuntimeException::class);
        try {
            $this->ejecutar();
        } finally {
            $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
            $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        }
    }

    public static function invalidaciones(): array
    {
        return [['revocado'], ['vencido'], ['programacion_aba'], ['estado'], ['correo']];
    }

    private function cambiar(string $cambio): void
    {
        match ($cambio) {
            'revocado' => DB::table('enlaces_asistencia_turno')->update(['revocado_at' => CarbonImmutable::now()]),
            'vencido' => CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay()),
            'estado' => DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CANCELADO']),
            'correo' => $this->turno->paciente->user->update(['email' => 'nuevo@example.test']),
            'programacion_aba' => $this->aba(),
        };
    }

    private function aba(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-07 09:00:00']);
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-07 10:00:00']);
    }

    #[DataProvider('invalidaciones')]
    public function test_segunda_validacion_detecta_cambios_antes_del_envio_y_no_reemite(string $cambio): void
    {
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andReturnUsing(function (Closure $preparar) use ($cambio): Resultado {
                $this->cambiar($cambio);
                try {
                    $preparar();
                    $this->fail('No debe construirse contenido después de un cambio incompatible.');
                } catch (\DomainException) {
                    return Resultado::FALLO_DEFINITIVO;
                }
            })->getMock());
        $this->assertSame(Resultado::FALLO_DEFINITIVO, $this->ejecutar());
        $this->assertSame('CERRADO', SobreEntregaEnlace::sole()->estado);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public function test_aviso_ahora_informativo_conserva_control_del_sobre_preexistente(): void
    {
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($this->id);
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CONFIRMADO']);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(18));
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andReturnUsing(function (Closure $preparar): Resultado {
                $this->cambiar('revocado');
                try {
                    $preparar();
                    $this->fail('El aviso informativo no permite omitir una revocación existente.');
                } catch (\DomainException) {
                    return Resultado::FALLO_DEFINITIVO;
                }
            })->getMock());
        $this->assertSame(Resultado::FALLO_DEFINITIVO, $this->ejecutar());
        $this->assertSame('INVALIDADO', SobreEntregaEnlace::sole()->resultado);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public function test_interrupcion_y_excepcion_con_secretos_no_habilitan_reenvio(): void
    {
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andThrow(new RuntimeException('token=SECRETO CLINICO'))->getMock());
        try {
            $this->ejecutar();
            $this->fail('La interrupción debe informarse.');
        } catch (RuntimeException $error) {
            $this->assertSame('No pudo completarse el despacho del recordatorio.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame(Resultado::INCIERTO, $this->ejecutar());
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
        $this->assertStringNotContainsString('SECRETO', Auditoria::all()->toJson());
    }

    public function test_resultado_y_cierre_del_sobre_son_atomicos_ante_fallo_de_auditoria(): void
    {
        $this->transporte(Resultado::ACEPTADO, function (): void {
            DB::connection()->beforeExecuting(function (string $sql): void {
                if (str_starts_with($sql, 'update "intentos_recordatorio_turno"')) {
                    throw new RuntimeException('Fallo sintético posterior a SMTP.');
                }
            });
        });
        $this->expectException(RuntimeException::class);
        try {
            $this->ejecutar();
        } finally {
            $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
            $this->assertNotNull(SobreEntregaEnlace::sole()->material_cifrado);
            $this->assertSame(Resultado::INCIERTO, IntentoRecordatorioTurno::sole()->resultado);
        }
    }

    public function test_job_serializable_solo_referencias_y_replay_sin_segundo_envio(): void
    {
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($this->id);
        $this->transporte(Resultado::ACEPTADO);
        $job = new DespacharRecordatorioTurnoJob($this->id, $this->identidad);
        $payload = serialize($job);
        foreach (['?token=', 'material_cifrado', $this->turno->paciente->user->email, EnlaceAsistenciaTurno::sole()->token_hash] as $secreto) {
            $this->assertStringNotContainsString($secreto, $payload);
        }
        $restaurado = unserialize($payload);
        $restaurado->handle(app(DespacharRecordatorioTurnoService::class));
        $restaurado->handle(app(DespacharRecordatorioTurnoService::class));
        $this->assertSame(1, $restaurado->tries);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
    }
}
