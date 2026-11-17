<?php

namespace Tests\Feature\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Enums\EstadoTurnoEnum;
use App\Http\Middleware\ProtegerAccesoOfertaAdelanto;
use App\Jobs\EnviarConfirmacionAdelantoJob;
use App\Mail\ConfirmacionAdelantoMail;
use App\Models\Auditoria;
use App\Models\DespachoConfirmacionAdelanto;
use App\Models\DespachoOfertaAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Turnos\DespacharConfirmacionAdelantoService;
use App\Services\Turnos\PrepararConfirmacionAdelantoService;
use App\Services\Turnos\ProcesarIntencionCupoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Auth;
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
use Tests\Support\SimularEnvioOfertaAdelanto;
use Tests\TestCase;

class ConfirmacionAdelantoTest extends TestCase
{
    use CreaPerfilesUc07;
    use DatabaseMigrations;

    private Kinesiologa $profesional;

    private Turno $candidato;

    private OfertaAdelanto $oferta;

    private string $url;

    private string $cookie;

    private array $autorizacion;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite efímero sin PDO MySQL.');
        }
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'https://consultorio.example.test']);
        Mail::fake();
        Queue::fake();
        $this->ahora('2026-11-15 10:00:00');
        $this->profesional = $this->profesionalUc07();
        $this->candidato = Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00', 'motivo_solicitud' => 'MOTIVO PRIVADO',
        ])->fresh();
        $paciente = $this->pacienteUc07();
        $cancelado = Turno::factory()->for($this->profesional)->for($paciente)->create([
            'tipo' => 'SEGUIMIENTO', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-16 10:15:00', 'fin_at' => '2026-11-16 11:00:00',
        ]);
        app(CancelarTurnoAction::class)->ejecutar($paciente->user, $cancelado);
        $this->ahora('2026-11-15 12:00:00');
        app(ProcesarIntencionCupoService::class)->procesar(IntencionEvaluacionCupo::query()->sole()->id);
        $this->oferta = OfertaAdelanto::query()->sole();
        $despacho = DespachoOfertaAdelanto::query()->sole();
        $this->url = json_decode(Crypt::decryptString($despacho->material_cifrado), true)['url'];
        $this->autorizar();
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.Auditoria::class);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('estados')]
    public function test_pantalla_mis_turnos_y_agenda_reflejan_el_mismo_turno(string $estado, string $etiqueta): void
    {
        $this->candidato->estado = EstadoTurnoEnum::from($estado);
        $this->candidato->saveOrFail();
        $paciente = $this->candidato->paciente->user;
        $this->actingAs($paciente)->get(route('turnos.mis-turnos.index'))
            ->assertOk()->assertViewHas('proximos', fn ($p) => $p->first()->id === $this->candidato->id && $p->first()->inicio_at->toDateString() === '2026-11-23');
        $this->actingAs($this->profesional->user)->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-11-23']))
            ->assertOk()->assertViewHas('turnos', fn ($turnos) => in_array($this->candidato->id, array_column($turnos, 'id'), true));
        $respuesta = $this->aceptar()->assertOk()->assertSee('Se confirmó el cambio de horario')
            ->assertSee('16/11/2026 10:15:00')->assertSee('10:45:00')->assertSee($etiqueta)->assertDontSee('MOTIVO PRIVADO');
        if ($estado === 'PENDIENTE') {
            $respuesta->assertSee('Tu turno quedó Pendiente')->assertDontSee('asistencia confirmada');
        }
        $this->actingAs($paciente)->get(route('turnos.mis-turnos.index'))
            ->assertOk()->assertSee('16/11/2026')->assertSee('10:15')->assertSee($etiqueta)
            ->assertViewHas('proximos', fn ($p) => $p->total() === 1 && $p->first()->id === $this->candidato->id
                && $p->first()->inicio_at->format('Y-m-d H:i') === '2026-11-16 10:15' && $p->first()->estado->value === $estado);
        $this->get(route('turnos.mis-turnos.index', ['fecha_desde' => '2026-11-23', 'fecha_hasta' => '2026-11-23']))
            ->assertOk()->assertViewHas('proximos', fn ($p) => $p->total() === 0);
        $this->get(route('turnos.mis-turnos.show', $this->candidato))->assertOk()
            ->assertViewHas('detalle', fn ($d) => $d['estado']->value === $estado && $d['inicio_at']->format('Y-m-d H:i') === '2026-11-16 10:15');
        $this->actingAs($this->profesional->user)->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-11-16']))
            ->assertOk()->assertSee('10:15')->assertSee($etiqueta)
            ->assertViewHas('turnos', function ($turnos) use ($estado): bool {
                $turno = collect($turnos)->firstWhere('id', $this->candidato->id);

                return $turno !== null && $turno['estado']->value === $estado && $turno['inicio_at']->format('Y-m-d H:i') === '2026-11-16 10:15';
            });
        $this->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-11-23']))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => ! in_array($this->candidato->id, array_column($turnos, 'id'), true));
    }

    public static function estados(): array
    {
        return [['PENDIENTE', 'Pendiente'], ['CONFIRMADO', 'Confirmado']];
    }

    #[DataProvider('estados')]
    public function test_correo_simulado_sale_despues_del_commit_con_datos_de_agenda(string $estado, string $etiqueta): void
    {
        $fake = new class(Mail::getFacadeRoot()->manager) extends MailFake
        {
            public array $niveles = [];

            public array $resultadosObservados = [];

            public function send($view, array $data = [], $callback = null)
            {
                $this->niveles[] = DB::transactionLevel();
                $this->resultadosObservados[] = SalidaOfertaAdelanto::query()->sole()->resultado;
                parent::send($view, $data, $callback);
            }
        };
        Mail::swap($fake);
        $this->candidato->estado = EstadoTurnoEnum::from($estado);
        $this->candidato->saveOrFail();
        $this->aceptar()->assertOk();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertSame('PENDIENTE', DespachoConfirmacionAdelanto::query()->sole()->estado);
        $this->enviar();
        $this->assertSame([0], $fake->niveles);
        $this->assertSame([OfertaAdelanto::ACEPTADA], $fake->resultadosObservados);
        Mail::assertSent(ConfirmacionAdelantoMail::class, function ($mail) use ($etiqueta): bool {
            $html = $mail->render();
            foreach (['Se confirmó el cambio de horario', '16/11/2026 10:15:00', '10:45:00', $etiqueta,
                $this->profesional->user->name, config('app.timezone')] as $texto) {
                $this->assertStringContainsString($texto, $html);
            }
            $this->assertStringNotContainsString('MOTIVO PRIVADO', $html);
            $this->assertStringNotContainsString('?token=', $html);
            $mail->build();
            $this->assertSame('Cambio de horario confirmado — KinesioSoft', $mail->subject);
            $this->assertCount(1, $mail->to);

            return $mail->hasTo($this->candidato->paciente->user->email);
        });
        $this->assertSame('ENVIADO', DespachoConfirmacionAdelanto::query()->sole()->estado);
        $this->assertNull(DespachoConfirmacionAdelanto::query()->sole()->material_cifrado);
    }

    public function test_repetir_aceptacion_y_reanudar_encolado_no_duplica_despacho(): void
    {
        $this->aceptar()->assertOk();
        $this->aceptar()->assertOk();
        $this->artisan('turnos:procesar-cupos')->assertExitCode(0);
        $this->artisan('turnos:procesar-cupos')->assertExitCode(0);
        Queue::assertPushed(EnviarConfirmacionAdelantoJob::class, 2);
        $referencias = Queue::pushed(EnviarConfirmacionAdelantoJob::class);
        $this->assertCount(1, $referencias->pluck('aceptacionId')->unique());
        foreach ($referencias as $job) {
            Auth::forgetGuards();
            $job->handle(app(PrepararConfirmacionAdelantoService::class), app(DespacharConfirmacionAdelantoService::class));
        }
        $this->enviar();
        $this->aceptar()->assertOk();
        $this->assertDatabaseCount('despachos_confirmacion_adelanto', 1);
        $this->assertDatabaseCount('salidas_oferta_adelanto', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        Mail::assertSent(ConfirmacionAdelantoMail::class, 1);
    }

    #[DataProvider('fallos')]
    public function test_fallo_de_correo_se_audita_y_el_traslado_sigue_visible(string $fallo, string $estado): void
    {
        $fake = new SimularEnvioOfertaAdelanto(Mail::getFacadeRoot()->manager);
        $fake->resultados = [$fallo];
        Mail::swap($fake);
        $this->aceptar()->assertOk();
        $programacion = $this->candidato->fresh()->getRawOriginal();
        $this->enviar();
        $this->enviar();
        $this->assertSame(1, $fake->llamadas);
        $this->assertSame($estado, DespachoConfirmacionAdelanto::query()->sole()->estado);
        $this->assertSame(1, Auditoria::query()->where('evento', 'confirmacion_adelanto_fallo_envio')->count());
        $this->assertSame($programacion, $this->candidato->fresh()->getRawOriginal());
        $this->assertSame(OfertaAdelanto::ACEPTADA, $this->oferta->fresh()->estado);
        $this->assertVisibleEnMisTurnos();
    }

    public static function fallos(): array
    {
        return [['temporal', 'FALLIDO'], ['definitivo', 'FALLIDO'], ['incierto', 'INCIERTO']];
    }

    public function test_rollback_del_traslado_no_deja_confirmacion_ni_envio(): void
    {
        $antes = $this->snapshot();
        Auditoria::creating(function (Auditoria $auditoria): void {
            if ($auditoria->evento === 'turno_adelantado_por_oferta') {
                $this->assertDatabaseCount('despachos_confirmacion_adelanto', 1);
                Mail::assertNothingSent();
                throw new RuntimeException('Fallo sintético tras preparar confirmación');
            }
        });
        $this->aceptar()->assertStatus(503);
        $this->assertSame($antes, $this->snapshot());
        $this->assertDatabaseCount('despachos_confirmacion_adelanto', 0);
        $this->artisan('turnos:procesar-cupos')->assertExitCode(0);
        Queue::assertNotPushed(EnviarConfirmacionAdelantoJob::class);
        Mail::assertNothingSent();
    }

    public function test_no_permite_transporte_dentro_de_una_transaccion(): void
    {
        $this->aceptar()->assertOk();
        $id = DespachoConfirmacionAdelanto::query()->sole()->id;
        try {
            DB::transaction(fn () => app(DespacharConfirmacionAdelantoService::class)->despachar($id));
            $this->fail('No debe enviar antes del commit.');
        } catch (LogicException) {
            Mail::assertNothingSent();
            $this->assertSame('PENDIENTE', DespachoConfirmacionAdelanto::query()->sole()->estado);
        }
        $this->enviar();
        Mail::assertSent(ConfirmacionAdelantoMail::class, 1);
    }

    public function test_get_y_head_de_aceptacion_solo_recuperan_resultado_sin_despachar(): void
    {
        $this->aceptar()->assertOk();
        $antes = $this->snapshot();
        $this->get($this->url)->assertOk()->assertSee('16/11/2026 10:15:00')->assertSee('Confirmado');
        $this->head($this->url)->assertOk();
        $this->assertSame($antes, $this->snapshot());
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    #[DataProvider('cambiosAntesDeEnvio')]
    public function test_no_envia_datos_a_destinatario_obsoleto_ni_turno_modificado(string $caso): void
    {
        $this->aceptar()->assertOk();
        if ($caso === 'destinatario') {
            $this->candidato->paciente->user->update(['email' => 'otro@example.test']);
        } else {
            DB::table('turnos')->where('id', $this->candidato->id)->update(['estado' => 'CANCELADO']);
        }
        $this->enviar();
        Mail::assertNothingSent();
        $this->assertSame('OMITIDO', DespachoConfirmacionAdelanto::query()->sole()->estado);
        $this->assertSame(1, Auditoria::query()->where('evento', 'confirmacion_adelanto_fallo_envio')->count());
    }

    public static function cambiosAntesDeEnvio(): array
    {
        return [['destinatario'], ['programacion']];
    }

    public function test_resultado_de_envio_interrumpido_no_provoca_segundo_transporte(): void
    {
        $this->aceptar()->assertOk();
        Auditoria::creating(function (Auditoria $auditoria): void {
            if ($auditoria->evento === 'confirmacion_adelanto_enviada') {
                throw new RuntimeException('Interrupción después del transporte');
            }
        });
        try {
            $this->enviar();
            $this->fail('Debió interrumpirse el registro del resultado.');
        } catch (RuntimeException) {
            $this->assertSame('INCIERTO', DespachoConfirmacionAdelanto::query()->sole()->estado);
        }
        Event::forget('eloquent.creating: '.Auditoria::class);
        $this->enviar();
        Mail::assertSent(ConfirmacionAdelantoMail::class, 1);
        $this->assertVisibleEnMisTurnos();
    }

    public function test_transporte_log_se_rechaza_y_su_fallo_no_revierte_el_cambio(): void
    {
        $this->aceptar()->assertOk();
        config(['mail.default' => 'log']);
        $this->enviar();
        Mail::assertNothingSent();
        $this->assertSame('FALLIDO', DespachoConfirmacionAdelanto::query()->sole()->estado);
        $this->assertSame(1, Auditoria::query()->where('evento', 'confirmacion_adelanto_fallo_envio')->count());
        $this->assertVisibleEnMisTurnos();
    }

    private function autorizar(): void
    {
        $respuesta = $this->get($this->url)->assertOk();
        $this->cookie = $respuesta->headers->getCookies()[0]->getValue();
        $this->autorizacion = json_decode(Crypt::decryptString($this->cookie), true);
    }

    private function aceptar()
    {
        return $this->withUnencryptedCookies([ProtegerAccesoOfertaAdelanto::COOKIE => $this->cookie])
            ->post('https://consultorio.example.test/turnos/adelanto/aceptar', [
                '_csrf_oferta' => $this->autorizacion['csrf'], 'accion' => 'aceptar',
            ], ['Origin' => 'https://consultorio.example.test']);
    }

    private function enviar(): void
    {
        Auth::forgetGuards();
        (new EnviarConfirmacionAdelantoJob(SalidaOfertaAdelanto::query()->sole()->id))->handle(
            app(PrepararConfirmacionAdelantoService::class), app(DespacharConfirmacionAdelantoService::class));
    }

    private function assertVisibleEnMisTurnos(): void
    {
        $this->actingAs($this->candidato->paciente->user)->get(route('turnos.mis-turnos.index'))
            ->assertOk()->assertViewHas('proximos', fn ($p) => $p->first()->id === $this->candidato->id
                && $p->first()->inicio_at->format('Y-m-d H:i:s') === '2026-11-16 10:15:00');
    }

    private function ahora(string $instante): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($instante, config('app.timezone')));
    }

    private function snapshot(): array
    {
        return array_map(fn ($tabla) => DB::table($tabla)->orderBy('id')->get()->toJson(), [
            'turnos', 'ofertas_adelanto', 'salidas_oferta_adelanto', 'despachos_confirmacion_adelanto', 'intenciones_evaluacion_cupo', 'auditorias',
        ]);
    }
}
