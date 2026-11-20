<?php

namespace Tests\Feature\Services\Turnos;

use App\Actions\Turnos\ActivarOfertaAdelantoAction;
use App\Actions\Turnos\CancelarTurnoAction;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Jobs\DespacharOfertaAdelantoJob;
use App\Jobs\ProcesarIntencionCupoJob;
use App\Mail\OfertaAdelantoMail;
use App\Models\Auditoria;
use App\Models\BloqueoAgenda;
use App\Models\DespachoOfertaAdelanto;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\EvaluacionAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\Turno;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\DespacharOfertaAdelantoService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\ProcesarIntencionCupoService;
use App\Services\Turnos\ResolverAsistenciaEnlaceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\MailFake;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class PrimeraEmisionAdelantoTest extends TestCase
{
    use CreaPerfilesUc07;

    // Commits reales dentro de SQLite en memoria: el envío debe observar nivel cero.
    use DatabaseMigrations;

    private Kinesiologa $profesional;

    private Turno $candidato;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        // Patrón de AtomicidadPrimeraConsultaTest: descartar la conexión efímera,
        // sin ejecutar downs históricos ajenos a estos fixtures administrativos.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Requiere SQLite en memoria sin PDO MySQL.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://consultorio.example.test']);
        $this->ahora('2026-11-15 22:00:00');
        $this->profesional = $this->profesionalUc07();
        $this->candidato = Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00',
            'motivo_solicitud' => 'MOTIVO CLINICO QUE NO DEBE SALIR',
        ])->fresh();
        Mail::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.DespachoOfertaAdelanto::class);
        Event::forget('eloquent.creating: '.Auditoria::class);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('origenes')]
    public function test_primera_emision_desde_ambos_canales_y_tipos_sin_mover_el_turno(string $canal, string $tipo): void
    {
        $intencion = $this->cancelar($canal, $tipo);
        $this->assertNull($intencion->procesado_at);
        $this->assertDatabaseCount('evaluaciones_adelanto', 0);
        $this->assertDatabaseCount('ofertas_adelanto', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $antes = $this->candidato->getAttributes();
        $this->ahora('2026-11-16 06:00:00');
        $evaluacion = $this->procesar($intencion);
        $this->assertSame('oferta_preparada', $evaluacion->resultado);
        $this->assertNotNull($intencion->fresh()->procesado_at);
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $this->assertSame('PENDIENTE', $despacho->estado);
        Mail::assertNothingSent();
        $oferta = OfertaAdelanto::query()->sole();
        $this->assertSame('08:00:00', $oferta->vence_at->format('H:i:s'));
        (new DespacharOfertaAdelantoJob($despacho->id))->handle(app(DespacharOfertaAdelantoService::class));
        Mail::assertSent(OfertaAdelantoMail::class, 1);
        $this->assertSame('ENVIADO', $despacho->fresh()->estado);
        $this->assertNull($despacho->fresh()->material_cifrado);
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public static function origenes(): array
    {
        return [['MIS_TURNOS', 'PRIMERA_CONSULTA'], ['MIS_TURNOS', 'SEGUIMIENTO'],
            ['ENLACE_SEGURO', 'PRIMERA_CONSULTA'], ['ENLACE_SEGURO', 'SEGUIMIENTO']];
    }

    public function test_repetir_recupera_resultado_oferta_y_despacho_sin_renovar_ni_reenviar(): void
    {
        $intencion = $this->cancelar();
        $primera = $this->procesar($intencion);
        $oferta = OfertaAdelanto::query()->sole();
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $this->ahora('2026-11-15 22:30:00');
        $segunda = $this->procesar($intencion);
        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame($oferta->getAttributes(), $oferta->fresh()->getAttributes());
        $this->assertSame($despacho->getAttributes(), $despacho->fresh()->getAttributes());
        $this->enviar($despacho);
        $this->procesar($intencion);
        $this->enviar($despacho);
        Mail::assertSent(OfertaAdelantoMail::class, 1);
        $this->assertDatabaseCount('evaluaciones_adelanto', 1);
        $this->assertDatabaseCount('despachos_oferta_adelanto', 1);
    }

    public function test_interrupcion_despues_de_activar_y_antes_de_encolar_se_recupera_por_sondeo(): void
    {
        $intencion = $this->cancelar();
        $this->procesar($intencion); // Se corta aquí, antes de ejecutar/encolar el correo.
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $hash = $despacho->token_hash;
        $this->artisan('turnos:procesar-cupos')->assertSuccessful();
        Queue::assertNotPushed(ProcesarIntencionCupoJob::class);
        Queue::assertPushed(DespacharOfertaAdelantoJob::class, fn ($job): bool => $job->despachoId === $despacho->id && $job->connection === 'database');
        $this->enviar($despacho);
        $this->assertSame($hash, $despacho->fresh()->token_hash);
        Mail::assertSent(OfertaAdelantoMail::class, 1);
    }

    public function test_fallo_al_preparar_entrega_revierte_activacion_evaluacion_y_marca_de_intencion(): void
    {
        $intencion = $this->cancelar();
        DespachoOfertaAdelanto::creating(fn () => throw new RuntimeException('Interrupción sintética'));
        try {
            $this->procesar($intencion);
            $this->fail('Debió revertirse la preparación completa.');
        } catch (RuntimeException $error) {
            $this->assertSame('Interrupción sintética', $error->getMessage());
        }
        $this->assertDatabaseCount('ofertas_adelanto', 0);
        $this->assertDatabaseCount('evaluaciones_adelanto', 0);
        $this->assertDatabaseCount('despachos_oferta_adelanto', 0);
        $this->assertNull($intencion->fresh()->procesado_at);
        Event::forget('eloquent.creating: '.DespachoOfertaAdelanto::class);
        $this->assertSame('oferta_preparada', $this->procesar($intencion)->resultado);
    }

    public function test_sin_candidatos_registra_resultado_y_repetir_no_inicia_otra_evaluacion(): void
    {
        $intencion = $this->cancelar();
        $this->candidato->forceFill(['estado' => 'CANCELADO'])->save();
        $evaluacion = $this->procesar($intencion);
        $this->assertSame('sin_candidatos', $evaluacion->resultado);
        $this->candidato->forceFill(['estado' => 'CONFIRMADO'])->save();
        $this->assertSame($evaluacion->id, $this->procesar($intencion)->id);
        $this->assertDatabaseCount('ofertas_adelanto', 0);
        $this->assertDatabaseCount('despachos_oferta_adelanto', 0);
    }

    #[DataProvider('margenes')]
    public function test_margen_cuatro_horas_se_revalida_al_consumir(string $hora, string $resultado): void
    {
        $intencion = $this->cancelar();
        $this->ahora('2026-11-16 '.$hora);
        $this->assertSame($resultado, $this->procesar($intencion)->resultado);
        $this->assertDatabaseCount('ofertas_adelanto', $resultado === 'oferta_preparada' ? 1 : 0);
    }

    public static function margenes(): array
    {
        return [['06:00:00', 'oferta_preparada'], ['06:00:01', 'no_activable']];
    }

    public function test_perdida_de_disponibilidad_despues_de_seleccionar_impide_reserva(): void
    {
        $intencion = $this->cancelar();
        $intercalado = false;
        DB::listen(function (QueryExecuted $evento) use (&$intercalado): void {
            if (! $intercalado && str_contains($evento->sql, 'select exists') && str_contains($evento->sql, '"paciente_id"')) {
                $intercalado = true;
                BloqueoAgenda::factory()->for($this->profesional)->create([
                    'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 11:00:00',
                ]);
            }
        });
        $this->assertSame('no_activable', $this->procesar($intencion)->resultado);
        $this->assertTrue($intercalado);
        $this->assertDatabaseCount('ofertas_adelanto', 0);
    }

    public function test_cancelacion_con_un_segundo_menos_de_doce_horas_no_crea_intencion(): void
    {
        $this->ahora('2026-11-15 22:00:01');
        try {
            $this->cancelar();
            $this->fail('No debe cancelar fuera del plazo.');
        } catch (CancelacionTurnoNoPermitidaException) {
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public function test_consumidor_vuelve_a_verificar_las_doce_horas_del_hecho_registrado(): void
    {
        $intencion = $this->cancelar();
        // Corrupción controlada: el consumidor no debe confiar sólo en que haya una fila.
        $hecho = $intencion->cancelacion;
        $nuevos = $hecho->valores_nuevos;
        $nuevos['ocurrido_at'] = $this->fecha('2026-11-15 22:00:01')->toIso8601String();
        DB::table('auditorias')->where('id', $hecho->id)->update(['valores_nuevos' => json_encode($nuevos)]);
        $this->assertSame('cancelacion_no_elegible', $this->procesar($intencion)->resultado);
        $this->assertDatabaseCount('ofertas_adelanto', 0);
    }

    public function test_recupera_oferta_preexistente_del_subpaso_cuatro_sin_renovar_plazo(): void
    {
        $intencion = $this->cancelar();
        $this->ahora('2026-11-16 05:00:00');
        $oferta = app(ActivarOfertaAdelantoAction::class)->ejecutar($intencion->id, $this->candidato->id);
        $this->ahora('2026-11-16 06:30:00'); // Ya no permitiría activar otra oferta.
        $evaluacion = $this->procesar($intencion);
        $this->assertSame($oferta->id, $evaluacion->oferta_id);
        $this->assertTrue($oferta->vence_at->equalTo(DespachoOfertaAdelanto::query()->sole()->vence_at));
        $this->assertDatabaseCount('ofertas_adelanto', 1);
    }

    public function test_correo_y_enlace_solo_contienen_agenda_y_se_envian_fuera_de_transaccion(): void
    {
        $intencion = $this->cancelar();
        $this->profesional->user->forceFill(['name' => 'Profesional', 'apellido' => 'De prueba'])->save();
        $this->ahora('2026-11-16 06:00:00');
        $this->procesar($intencion);
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $datos = json_decode(Crypt::decryptString($despacho->material_cifrado), true);
        parse_str(parse_url($datos['url'], PHP_URL_QUERY), $query);
        $token = $query['token'];
        $this->assertSame(hash('sha256', $token), $despacho->token_hash);
        $this->assertSame((int) $despacho->oferta_id, $datos['oferta_id']);
        $this->assertSame((int) $this->candidato->paciente_id, $datos['paciente_id']);
        $this->assertStringNotContainsString($token, $despacho->toJson());
        $this->assertStringNotContainsString($token, Auditoria::query()->get()->toJson());
        // Observa la frontera real sin sustituir el Mailer usado para renderizar Blade.
        $fake = new class(Mail::getFacadeRoot()->manager) extends MailFake
        {
            public ?int $nivel = null;

            public ?OfertaAdelantoMail $mensaje = null;

            public function send($view, array $data = [], $callback = null)
            {
                $this->nivel = DB::transactionLevel();
                $this->mensaje = $view;
                parent::send($view, $data, $callback);
            }
        };
        Mail::swap($fake);
        $this->enviar($despacho);
        $this->assertSame(0, $fake->nivel);
        $mail = $fake->mensaje;
        $this->assertNotNull($mail);
        $html = $mail->render();
        foreach (['Lunes 23/11/2026', 'Lunes 16/11/2026', '<strong>10:00</strong>', 'Profesional De prueba',
            '16/11/2026 a las 08:00 hs.', $this->candidato->paciente->user->name, $datos['url']] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('10:45:00', $html);
        $this->assertStringNotContainsString('10:30', $html);
        $this->assertStringNotContainsString('08:00:00.000000', $html);
        $this->assertStringNotContainsString('MOTIVO CLINICO', $html);
        $this->assertStringNotContainsString($datos['url'], json_encode($mail));
        $this->assertSame($this->candidato->paciente->user->email, $mail->to[0]['address']);
    }

    #[DataProvider('invalidacionesEntrega')]
    public function test_no_envia_material_vencido_o_que_ya_no_corresponde(string $caso): void
    {
        $intencion = $this->cancelar();
        $this->procesar($intencion);
        $despacho = DespachoOfertaAdelanto::query()->sole();
        if ($caso === 'vencimiento') {
            CarbonImmutable::setTestNow($despacho->vence_at);
        } elseif ($caso === 'correo') {
            $this->candidato->paciente->user->update(['email' => 'otro@example.test']);
        } else {
            $this->candidato->forceFill(['estado' => 'CANCELADO'])->save();
        }
        $this->enviar($despacho);
        Mail::assertNothingSent();
        $this->assertSame('OMITIDO', $despacho->fresh()->estado);
    }

    public static function invalidacionesEntrega(): array
    {
        return [['vencimiento'], ['correo'], ['turno']];
    }

    public function test_fallo_de_transporte_no_filtra_url_y_no_reenvia_automaticamente(): void
    {
        $intencion = $this->cancelar();
        $this->procesar($intencion);
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $url = json_decode(Crypt::decryptString($despacho->material_cifrado), true)['url'];
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException($url));
        try {
            $this->enviar($despacho);
            $this->fail('Debió informar resultado incierto.');
        } catch (RuntimeException $error) {
            $this->assertStringNotContainsString($url, $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        $this->assertSame('INCIERTO', $despacho->fresh()->estado);
        $this->enviar($despacho); // No vuelve a llamar al transporte.
        $this->artisan('turnos:procesar-cupos')->assertSuccessful();
        Queue::assertNotPushed(DespacharOfertaAdelantoJob::class);
    }

    public function test_comando_y_job_encolan_referencias_en_database_aun_con_sync_predeterminado(): void
    {
        $intencion = $this->cancelar();
        config(['queue.default' => 'sync']);
        $this->artisan('turnos:procesar-cupos')->assertSuccessful();
        Queue::assertPushed(ProcesarIntencionCupoJob::class, fn ($job): bool => $job->intencionId === $intencion->id && $job->connection === 'database');
        $this->assertDatabaseCount('evaluaciones_adelanto', 0);
        (new ProcesarIntencionCupoJob($intencion->id))->handle(app(ProcesarIntencionCupoService::class));
        Queue::assertPushed(DespacharOfertaAdelantoJob::class, fn ($job): bool => $job->connection === 'database');
        Mail::assertNothingSent();
        $job = new DespacharOfertaAdelantoJob(DespachoOfertaAdelanto::query()->sole()->id);
        $this->assertStringNotContainsString('token', serialize($job));
        $this->assertStringNotContainsString('example.test', serialize($job));
    }

    public function test_scheduler_independiente_y_correo_no_serializable(): void
    {
        $evento = collect(app(Schedule::class)->events())->first(fn ($e): bool => str_contains($e->command ?? '', 'turnos:procesar-cupos'));
        $this->assertSame('* * * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
        $this->assertSame(config('app.timezone'), $evento->timezone);
        $this->expectException(LogicException::class);
        serialize(new OfertaAdelantoMail(1, ['url' => 'https://example.test/?token=secreto']));
    }

    public function test_rechaza_transporte_log_sin_reclamar_ni_exponer_el_correo(): void
    {
        $this->procesar($this->cancelar());
        $despacho = DespachoOfertaAdelanto::query()->sole();
        config(['mail.default' => 'log']);
        try {
            $this->enviar($despacho);
            $this->fail('No debe entregar credenciales al transporte log.');
        } catch (LogicException) {
            $this->assertSame('PENDIENTE', $despacho->fresh()->estado);
            Mail::assertNothingSent();
        }
    }

    public function test_material_asociado_a_otro_paciente_no_se_puede_despachar(): void
    {
        $this->procesar($this->cancelar());
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $datos = json_decode(Crypt::decryptString($despacho->material_cifrado), true);
        $datos['paciente_id']++;
        DB::table('despachos_oferta_adelanto')->where('id', $despacho->id)->update([
            'material_cifrado' => Crypt::encryptString(json_encode($datos)),
        ]);
        try {
            $this->enviar($despacho);
            $this->fail('Debe rechazar el material que no corresponde al destinatario.');
        } catch (RuntimeException $error) {
            $this->assertSame('El material de entrega de la oferta no es válido.', $error->getMessage());
            $this->assertNull($error->getPrevious());
            $this->assertSame('PENDIENTE', $despacho->fresh()->estado);
            Mail::assertNothingSent();
        }
    }

    private function procesar(IntencionEvaluacionCupo $intencion): EvaluacionAdelanto
    {
        return app(ProcesarIntencionCupoService::class)->procesar($intencion->id);
    }

    private function enviar(DespachoOfertaAdelanto $despacho): void
    {
        app(DespacharOfertaAdelantoService::class)->despachar($despacho->id);
    }

    private function cancelar(string $canal = 'MIS_TURNOS', string $tipo = 'SEGUIMIENTO'): IntencionEvaluacionCupo
    {
        $paciente = $this->pacienteUc07();
        $turno = Turno::factory()->for($this->profesional)->for($paciente)->create([
            'tipo' => $tipo, 'estado' => 'PENDIENTE',
            'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 10:45:00',
        ])->fresh();
        if ($canal === 'MIS_TURNOS') {
            app(CancelarTurnoAction::class)->ejecutar($paciente->user, $turno);
        } else {
            $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($turno->id);
            $enlace = EnlaceAsistenciaTurno::query()->findOrFail($emitido->enlaceId);
            [$datos] = app(AutorizacionNavegadorEnlaceService::class)->crear($enlace);
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'cancelar');
        }

        return IntencionEvaluacionCupo::query()->where('turno_id', $turno->id)->sole();
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('app.timezone'));
    }

    private function ahora(string $valor): void
    {
        CarbonImmutable::setTestNow($this->fecha($valor));
    }
}
