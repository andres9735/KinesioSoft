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
use App\Services\Turnos\GestionarReintentosRecordatorioTurnoService;
use App\Services\Turnos\OrquestarRecordatoriosTurnoService;
use App\Services\Turnos\PrepararContenidoRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\SobreEntregaEnlaceService;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\Support\CreaPerfilesUc07;
use Tests\Support\StreamSmtpRecordatorio;
use Tests\TestCase;

class GestionarReintentosRecordatorioTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;

    private Turno $turno;

    private int $id;

    private string $identidad;

    private GestionarReintentosRecordatorioTurnoService $gestion;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite en memoria sin PDO MySQL.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test',
            'recordatorios.horario_inicio' => null, 'recordatorios.horario_fin' => null]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        $this->id = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar()[0];
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($this->id);
        $this->identidad = (string) Str::uuid();
        $this->gestion = app(GestionarReintentosRecordatorioTurnoService::class);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function transporte(Resultado $resultado, ?Closure $durante = null): void
    {
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andReturnUsing(function (Closure $construir) use ($resultado, $durante): Resultado {
                $durante?->__invoke();
                $construir()->render();

                return $resultado;
            })->getMock());
    }

    private function enviar(Resultado $resultado, ?array $plan = null, ?Closure $durante = null): ?Resultado
    {
        $this->transporte($resultado, $durante);

        return app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $plan['identidad'] ?? $this->identidad, $plan['numero'] ?? 1);
    }

    private function avanzar(int $minutos): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes($minutos));
    }

    public function test_tres_reintentos_backoff_creciente_y_agotamiento_sin_reemision(): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $hash = EnlaceAsistenciaTurno::sole()->token_hash;
        $this->assertSame(Resultado::FALLO_RECUPERABLE, $this->enviar(Resultado::FALLO_RECUPERABLE));
        $vence = SobreEntregaEnlace::sole()->vence_at;
        foreach ([5, 10, 20] as $posicion => $minutos) {
            $this->assertSame([], $this->gestion->planificar());
            $this->avanzar($minutos - 1);
            $this->assertSame([], $this->gestion->planificar());
            $this->avanzar(1);
            $plan = $this->gestion->planificar()[0];
            $this->assertSame($posicion + 2, $plan['numero']);
            $this->assertSame([$plan], $this->gestion->planificar());
            $this->assertSame(Resultado::FALLO_RECUPERABLE, $this->enviar(Resultado::FALLO_RECUPERABLE, $plan));
            $this->assertTrue(SobreEntregaEnlace::sole()->vence_at->equalTo($vence));
        }
        $this->assertDatabaseCount('intentos_recordatorio_turno', 4);
        $this->assertSame([], $this->gestion->planificar());
        $this->assertSame('REINTENTOS_AGOTADOS', RecordatorioTurno::sole()->incidencia);
        $this->assertSame('CERRADO', SobreEntregaEnlace::sole()->estado);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->assertSame($hash, EnlaceAsistenciaTurno::sole()->token_hash);
        $this->assertNull(EnlaceAsistenciaTurno::sole()->revocado_at);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $auditorias = Auditoria::count();
        $this->gestion->planificar();
        $this->assertSame($auditorias, Auditoria::count());
    }

    public function test_limites_en_microsegundos_y_rechazo_de_job_anticipado(): void
    {
        $this->enviar(Resultado::FALLO_RECUPERABLE);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5)->subMicrosecond());
        $this->assertSame([], $this->gestion->planificar());
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldNotReceive('enviar')->getMock());
        $this->assertNull(app(DespacharRecordatorioTurnoService::class)->despachar($this->id, (string) Str::uuid(), 2));
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMicrosecond());
        $this->assertSame(2, $this->gestion->planificar()[0]['numero']);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
    }

    public function test_ventana_desde_primer_fallo_no_se_extiende_y_depura_material(): void
    {
        $this->enviar(Resultado::FALLO_RECUPERABLE);
        $this->avanzar(30);
        $plan = $this->gestion->planificar()[0];
        $this->enviar(Resultado::FALLO_RECUPERABLE, $plan);
        $this->avanzar(30);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMicrosecond());
        $this->assertSame([], $this->gestion->planificar());
        $this->assertSame('VENTANA_REINTENTOS_AGOTADA', RecordatorioTurno::sole()->incidencia);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 2);
    }

    #[DataProvider('terminales')]
    public function test_no_reintenta_resultado_aceptado_definitivo_o_incierto(Resultado $resultado, string $estado): void
    {
        $this->enviar($resultado);
        $this->avanzar(5);
        $this->assertSame([], $this->gestion->planificar());
        $this->assertSame($estado, RecordatorioTurno::sole()->estado->value);
        $this->assertSame($resultado, app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $this->identidad));
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        if ($resultado === Resultado::INCIERTO) {
            $this->assertSame('RESULTADO_INCIERTO', RecordatorioTurno::sole()->incidencia);
            $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
        }
    }

    public static function terminales(): array
    {
        return [[Resultado::ACEPTADO, 'PROCESADO'], [Resultado::FALLO_DEFINITIVO, 'FALLIDO'], [Resultado::INCIERTO, 'INCIERTO']];
    }

    public function test_jobs_duplicados_y_workers_intercalados_no_reclaman_dos_veces(): void
    {
        $intercalado = 'no ejecutado';
        $this->enviar(Resultado::FALLO_RECUPERABLE, durante: function () use (&$intercalado): void {
            $intercalado = app(DespacharRecordatorioTurnoService::class)->despachar($this->id, (string) Str::uuid());
        });
        $this->assertNull($intercalado);
        $this->avanzar(5);
        $plan = $this->gestion->planificar()[0];
        $this->assertNull(app(DespacharRecordatorioTurnoService::class)->despachar($this->id, (string) Str::uuid(), 1));
        $this->enviar(Resultado::ACEPTADO, $plan, function () use ($plan, &$intercalado): void {
            $intercalado = app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $plan['identidad'], 2);
        });
        $this->assertSame(Resultado::INCIERTO, $intercalado);
        $this->assertSame(Resultado::ACEPTADO, app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $plan['identidad'], 2));
        $this->assertDatabaseCount('intentos_recordatorio_turno', 2);
    }

    #[DataProvider('cambios')]
    public function test_cambio_entre_intentos_impide_envio_y_elimina_material(string $cambio): void
    {
        $this->enviar(Resultado::FALLO_RECUPERABLE);
        $this->avanzar(5);
        $plan = $this->gestion->planificar()[0];
        match ($cambio) {
            'revocado' => DB::table('enlaces_asistencia_turno')->update(['revocado_at' => CarbonImmutable::now()]),
            'estado' => DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'EN_ATENCION']),
            'correo' => $this->turno->paciente->user->update(['email' => 'otro@example.test']),
            'correo_invalido' => $this->turno->paciente->user->update(['email' => 'invalido']),
            'reprogramacion' => $this->aba(),
            'cerrado' => app(SobreEntregaEnlaceService::class)->finalizarNecesidad((int) SobreEntregaEnlace::sole()->id),
            'vencido' => app(SobreEntregaEnlaceService::class)->limitarVigencia((int) SobreEntregaEnlace::sole()->id, CarbonImmutable::now()),
        };
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldNotReceive('enviar')->getMock());
        $this->assertNull(app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $plan['identidad'], 2));
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public static function cambios(): array
    {
        return [['revocado'], ['estado'], ['correo'], ['correo_invalido'], ['reprogramacion'], ['cerrado'], ['vencido']];
    }

    private function aba(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-07 09:00:00']);
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-07 10:00:00']);
    }

    public function test_planificacion_repara_fallo_de_encolado_con_misma_identidad_y_sin_reemitir(): void
    {
        $primero = $this->gestion->planificar()[0];
        $bus = app(Dispatcher::class);
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Cola no disponible.'));
        try {
            app(OrquestarRecordatoriosTurnoService::class)->ejecutar();
            $this->fail('La indisponibilidad debe informarse.');
        } catch (RuntimeException $error) {
            $this->assertNull($error->getPrevious());
        } finally {
            Bus::swap($bus);
        }
        Queue::fake();
        app(OrquestarRecordatoriosTurnoService::class)->ejecutar();
        app(OrquestarRecordatoriosTurnoService::class)->ejecutar();
        Queue::assertPushed(DespacharRecordatorioTurnoJob::class, 2);
        Queue::assertPushed(DespacharRecordatorioTurnoJob::class, fn ($job) => $job->identidadIntento === $primero['identidad']);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
    }

    public function test_caida_tras_reclamo_sin_resultado_no_se_reenvia_y_expira_sin_borrar_historia(): void
    {
        $plan = $this->gestion->planificar()[0];
        $this->gestion->reclamar($this->id, $plan['identidad'], 1);
        $this->assertSame([], $this->gestion->planificar());
        $this->assertSame('RESULTADO_INCIERTO', RecordatorioTurno::sole()->incidencia);
        $this->assertSame(Resultado::INCIERTO, app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $plan['identidad']));
        $this->avanzar(24 * 60);
        $this->gestion->planificar();
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->assertSame(Resultado::INCIERTO, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertSame('INCIERTO', RecordatorioTurno::sole()->estado->value);
    }

    public function test_caida_despues_del_transporte_mantiene_sobre_e_intento_inciertos(): void
    {
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andReturnUsing(function (Closure $contenido): Resultado {
                $contenido()->render();
                DB::connection()->beforeExecuting(function (string $sql): void {
                    if (str_starts_with($sql, 'update "intentos_recordatorio_turno"')) {
                        throw new RuntimeException('Fallo posterior a SMTP.');
                    }
                });

                return Resultado::ACEPTADO;
            })->getMock());
        try {
            app(DespacharRecordatorioTurnoService::class)->despachar($this->id, $this->identidad);
            $this->fail('El fallo de persistencia debe informarse.');
        } catch (RuntimeException $error) {
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame([], $this->gestion->planificar());
        $this->assertSame(Resultado::INCIERTO, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
    }

    public function test_reintento_smtp_simulado_usa_mismo_token_y_registra_solo_aceptacion(): void
    {
        $hash = EnlaceAsistenciaTurno::sole()->token_hash;
        $this->enviar(Resultado::FALLO_RECUPERABLE);
        $this->avanzar(5);
        $plan = $this->gestion->planificar()[0];
        config(['mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1025,
            'url' => null, 'scheme' => null, 'username' => null, 'password' => null],
            'mail.from.address' => 'consultorio@example.test']);
        $stream = new StreamSmtpRecordatorio;
        $this->instance(TransportarRecordatorioTurnoService::class, new class($stream) extends TransportarRecordatorioTurnoService
        {
            public function __construct(private StreamSmtpRecordatorio $stream) {}

            protected function crearTransporte(string $host, int $puerto): SmtpTransport
            {
                return new SmtpTransport($this->stream);
            }
        });
        $job = new DespacharRecordatorioTurnoJob($this->id, $plan['identidad'], 2);
        $payload = serialize($job);
        $job->handle(app(DespacharRecordatorioTurnoService::class));
        $job->handle(app(DespacharRecordatorioTurnoService::class));
        $cuerpo = quoted_printable_decode(implode('', $stream->escrituras));
        $this->assertTrue((bool) preg_match('/\?token=([a-f0-9]{64})/', $cuerpo, $credencial));
        $this->assertSame($hash, hash('sha256', $credencial[1]));
        $this->assertStringNotContainsString($credencial[1], $payload);
        $this->assertStringNotContainsString($credencial[1], Auditoria::all()->toJson());
        $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $cuerpo);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 2);
        $this->assertSame(Resultado::ACEPTADO, IntentoRecordatorioTurno::orderByDesc('numero')->first()->resultado);
        $this->assertNull(IntentoRecordatorioTurno::orderByDesc('numero')->first()->evidencia_entrega);
    }

    public function test_payload_anterior_sin_numero_conserva_semantica_inicial_y_replay(): void
    {
        $job = new DespacharRecordatorioTurnoJob($this->id, $this->identidad);
        $payload = str_replace('s:13:"numeroIntento";i:1;', '', serialize($job));
        $payload = preg_replace_callback('/^(O:\d+:"[^"]+":)(\d+)(:\{)/', fn ($m) => $m[1].((int) $m[2] - 1).$m[3], $payload);
        $anterior = unserialize($payload);
        $this->transporte(Resultado::ACEPTADO);
        $anterior->handle(app(DespacharRecordatorioTurnoService::class));
        $anterior->handle(app(DespacharRecordatorioTurnoService::class));
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        $this->assertSame(Resultado::ACEPTADO, IntentoRecordatorioTurno::sole()->resultado);
    }

    public function test_reclamo_incierto_del_sobre_no_se_reabre_aunque_previo_fallo_sea_conocido(): void
    {
        $this->enviar(Resultado::FALLO_RECUPERABLE);
        $this->avanzar(5);
        app(SobreEntregaEnlaceService::class)->conMaterialParaDespacho((int) SobreEntregaEnlace::sole()->id, fn () => null);
        $this->assertSame([], $this->gestion->planificar());
        $this->assertSame('INCIERTO', RecordatorioTurno::sole()->estado->value);
        $this->assertSame('RESULTADO_INCIERTO', RecordatorioTurno::sole()->incidencia);
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
        $this->assertSame(Resultado::FALLO_RECUPERABLE, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
    }
}
