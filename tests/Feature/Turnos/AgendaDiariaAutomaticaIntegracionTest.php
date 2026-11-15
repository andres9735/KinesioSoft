<?php

namespace Tests\Feature\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Enums\DiaSemanaEnum;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Jobs\DespacharRecordatorioTurnoJob;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntentoRecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Agenda\GenerarAgendaDiariaService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\PrepararContenidoRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use Mockery;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

/** EI aprobados: integración local; no sustituye carreras MariaDB ni consumidores externos. */
class AgendaDiariaAutomaticaIntegracionTest extends TestCase
{
    use CreaPerfilesUc07;

    private string $url;

    private string $csrf;

    private int $recordatorio;

    protected function setUp(): void
    {
        parent::setUp();
        $handlers = app('log')->channel()->getLogger()->getHandlers();
        if (! app()->environment('testing') || app()->configurationIsCached()
            || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || \PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('pdo_mysql')
            || count($handlers) !== 1 || ! $handlers[0] instanceof NullHandler) {
            throw new LogicException('Los EI requieren el perfil SQLite aislado con NullHandler.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->withoutVite();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        $this->ahora('2026-11-06 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function ahora(string $fecha): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($fecha, config('app.timezone')));
    }

    private function turno(int $horas = 15, string $estado = 'PENDIENTE'): Turno
    {
        $inicio = CarbonImmutable::now()->addHours($horas);

        return Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => $estado,
            'inicio_at' => $inicio->format('Y-m-d H:i:s'), 'fin_at' => $inicio->addMinutes(30)->format('Y-m-d H:i:s'),
            'motivo_solicitud' => 'CLINICO-EI-NO-EXPONER',
        ])->fresh();
    }

    private function preparar(Turno $turno): void
    {
        $this->recordatorio = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar()[0];
        $this->url = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($turno->id)->urlParaCorreo();
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($this->recordatorio);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('sobres_entrega_enlace', 1);
    }

    private function autorizar(): void
    {
        $response = $this->get($this->url)->assertOk()->assertDontSee('CLINICO-EI-NO-EXPONER');
        preg_match('/name="_csrf_enlace" value="([a-f0-9]+)"/', $response->getContent(), $matches);
        $this->assertTrue(isset($matches[1]));
        $this->csrf = $matches[1];
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }

    private function decidir(string $accion): TestResponse
    {
        return $this->withHeaders(['Origin' => 'https://consultorio.example.test', 'Sec-Fetch-Site' => 'same-origin'])
            ->post('https://consultorio.example.test/turnos/asistencia/decidir', [
                '_csrf_enlace' => $this->csrf, 'accion' => $accion, 'acepto' => '1',
            ])->assertStatus(303)->assertHeader('Referrer-Policy', 'no-referrer');
    }

    private function interno(): void
    {
        Auth::forgetGuards();
        $this->app->instance('request', Request::create('/'));
    }

    private function agenda(Turno $turno, string $estado): void
    {
        $this->actingAs($turno->kinesiologa->user)->get(route('turnos.agenda-atencion.index', [
            'fecha' => $turno->inicio_at->toDateString(),
        ]))->assertOk()->assertViewHas('turnos', function (array $turnos) use ($turno, $estado): bool {
            $item = collect($turnos)->firstWhere('id', $turno->id);

            return $item !== null && $item['estado']->value === $estado;
        })->assertDontSee('CLINICO-EI-NO-EXPONER');
    }

    private function transporte(Resultado $resultado = Resultado::ACEPTADO, ?Closure $antes = null): void
    {
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->once()->andReturnUsing(function (Closure $construir) use ($resultado, $antes): Resultado {
                $this->assertSame(0, DB::transactionLevel());
                $antes?->__invoke();
                $html = $construir()->render();
                $this->assertFalse(str_contains($html, 'CLINICO-EI-NO-EXPONER'));
                parse_str((string) parse_url($this->url, PHP_URL_QUERY), $query);
                $this->assertTrue(EnlaceAsistenciaTurno::sole()->coincideConToken($query['token']));
                $this->assertTrue(str_contains($html, htmlspecialchars($this->url, ENT_QUOTES)));

                return $resultado;
            })->getMock());
    }

    public function test_ei01_alta_tardia_recordatorio_confirmable_y_agenda_sin_cancelacion(): void
    {
        $profesional = $this->profesionalUc07();
        $configuracion = $profesional->configuracionesAgenda()->first();
        $configuracion->franjasHorarias()->create(['dia_semana' => DiaSemanaEnum::VIERNES,
            'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00']);
        $turno = app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
            $this->pacienteUc07()->user, (string) Str::uuid(), $profesional->id,
            CarbonImmutable::now()->addHours(8), 'CLINICO-EI-NO-EXPONER',
        )->turno->fresh();
        $antes = $turno->getAttributes();
        $this->preparar($turno);
        $this->transporte();
        $job = new DespacharRecordatorioTurnoJob($this->recordatorio, (string) Str::uuid());
        app()->call([$job, 'handle']);
        $this->autorizar();
        $html = $this->get($this->url)->assertOk()->getContent();
        $this->assertTrue(str_contains($html, 'name="accion" value="confirmar"'));
        $this->assertFalse(str_contains($html, 'name="accion" value="cancelar"'));
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        $this->assertSame(Resultado::ACEPTADO, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->agenda($turno, 'PENDIENTE');
    }

    #[DataProvider('limiteDoceHoras')]
    public function test_ei02_limite_inclusivo_compartido_y_revalidacion_post(bool $exacto): void
    {
        $turno = $this->turno(12, 'CONFIRMADO');
        $this->preparar($turno);
        $this->autorizar();
        if (! $exacto) {
            $this->ahora('2026-11-06 10:00:00.000001');
        }
        $this->decidir('cancelar');
        $this->assertSame($exacto ? 'CANCELADO' : 'CONFIRMADO', $turno->fresh()->estado->value);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', $exacto ? 1 : 0);
        $this->agenda($turno, $exacto ? 'CANCELADO' : 'CONFIRMADO');
    }

    public static function limiteDoceHoras(): array
    {
        return [[true], [false]];
    }

    public function test_ei03_confirmacion_antes_de_inicio_y_replay_al_inicio(): void
    {
        $turno = $this->turno(1);
        $this->preparar($turno);
        $this->ahora('2026-11-06 10:59:59');
        $this->autorizar();
        $this->decidir('confirmar');
        $this->ahora('2026-11-06 11:00:00');
        $this->withHeaders(['Origin' => 'https://consultorio.example.test', 'Sec-Fetch-Site' => 'same-origin'])
            ->post('https://consultorio.example.test/turnos/asistencia/decidir', [
                '_csrf_enlace' => $this->csrf, 'accion' => 'confirmar', 'acepto' => '1',
            ])->assertStatus(410)->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('<meta name="referrer" content="no-referrer">', false);
        $this->assertSame(1, Auditoria::where('evento', 'turno_asistencia_confirmada')->count());
        $this->assertSame(1, Auditoria::where('evento', 'enlace_asistencia_resultado')->count());
        $this->agenda($turno, 'CONFIRMADO');
    }

    public function test_ei04_mismo_enlace_confirma_y_cancela_a_quince_horas(): void
    {
        $turno = $this->turno();
        $this->preparar($turno);
        $this->autorizar();
        $confirmacion = $this->decidir('confirmar');
        foreach ($confirmacion->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
        $this->get('https://consultorio.example.test/turnos/asistencia/resultado')->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('<meta name="referrer" content="no-referrer">', false);
        $this->decidir('cancelar');
        $this->decidir('cancelar'); // Respuesta repetida, no otra cancelación.
        $this->assertSame(1, Auditoria::where('evento', 'turno_cancelado')->count());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->get($this->url)->assertStatus(409)->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('<meta name="referrer" content="no-referrer">', false);
        $this->agenda($turno, 'CANCELADO');
    }

    public function test_ei05_aba_entre_preparacion_y_transporte_invalida_enlace_abierto(): void
    {
        $turno = $this->turno();
        $this->preparar($turno);
        $this->autorizar();
        $this->interno();
        $this->transporte(antes: function () use ($turno): void {
            DB::table('turnos')->where('id', $turno->id)->update(['inicio_at' => '2026-11-07 02:00:00']);
            DB::table('turnos')->where('id', $turno->id)->update(['inicio_at' => '2026-11-07 01:00:00']);
        });
        try {
            app()->call([new DespacharRecordatorioTurnoJob($this->recordatorio, (string) Str::uuid()), 'handle']);
            $this->fail('El despacho obsoleto debe rechazarse.');
        } catch (RuntimeException $error) {
            $this->assertSame('No pudo completarse el despacho del recordatorio.', $error->getMessage());
        }
        $this->get($this->url)->assertStatus(409);
        $this->decidir('confirmar');
        $this->assertSame('PENDIENTE', $turno->fresh()->estado->value);
        $this->assertSame(0, Auditoria::where('evento', 'turno_asistencia_confirmada')->count());
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public function test_ei06_resultado_incierto_y_repeticion_de_job_sin_otro_transporte(): void
    {
        $turno = $this->turno();
        $this->preparar($turno);
        $this->transporte(Resultado::INCIERTO);
        $job = new DespacharRecordatorioTurnoJob($this->recordatorio, (string) Str::uuid());
        app()->call([$job, 'handle']);
        app()->call([unserialize(serialize($job)), 'handle']);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        $this->assertSame(Resultado::INCIERTO, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertNull(IntentoRecordatorioTurno::sole()->evidencia_entrega);
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->estado);
        $this->agenda($turno, 'PENDIENTE');
    }

    public function test_ei07_preview_head_y_get_repetidos_no_cambian_asistencia(): void
    {
        $turno = $this->turno();
        $this->preparar($turno);
        $antes = $turno->getAttributes();
        $auditorias = Auditoria::count();
        for ($i = 0; $i < 2; $i++) {
            $this->head($this->url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
            $this->get($this->url)->assertOk()->assertHeader('Referrer-Policy', 'strict-origin')
                ->assertSee('<meta name="referrer" content="strict-origin">', false)
                ->assertSee('name="_csrf_enlace"', false);
        }
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->assertSame($auditorias, Auditoria::count());
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
        $this->assertNull(EnlaceAsistenciaTurno::sole()->revocado_at);
    }

    #[DataProvider('ordenesCancelacion')]
    public function test_ei08_canales_serializados_en_ambos_ordenes_una_sola_intencion(bool $portalPrimero): void
    {
        $turno = $this->turno();
        $this->preparar($turno);
        $this->autorizar();
        // Simula los dos órdenes consolidados; no acredita dos conexiones concurrentes.
        if ($portalPrimero) {
            app(CancelarTurnoAction::class)->ejecutar($turno->paciente->user, $turno);
        }
        $this->decidir('cancelar');
        $replay = app(CancelarTurnoAction::class)->ejecutar($turno->paciente->user, $turno);
        $this->assertTrue($replay->esReplay);
        $this->assertSame('CANCELADO', $turno->fresh()->estado->value);
        $this->assertSame(1, Auditoria::where('evento', 'turno_cancelado')->count());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->assertNull(DB::table('intenciones_evaluacion_cupo')->sole()->procesado_at);
        $this->agenda($turno, 'CANCELADO');
    }

    public static function ordenesCancelacion(): array
    {
        return [[true], [false]];
    }

    public function test_ei09_generacion_fallida_y_alta_posterior_visible_sin_dependencia(): void
    {
        $this->profesionalUc07();
        $fallar = true;
        DB::listen(function (QueryExecuted $query) use (&$fallar): void {
            if ($fallar && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"kinesiologas"')) {
                $fallar = false;
                throw new RuntimeException('Fallo sintético de selección');
            }
        });
        $this->ahora('2026-11-06 07:00:00');
        $ejecucion = app(GenerarAgendaDiariaService::class)->ejecutar();
        $this->assertFalse($fallar);
        $this->assertSame('FALLIDA', $ejecucion->resultado->value);
        $this->ahora('2026-11-06 10:00:00');
        $turno = $this->turno(8);
        $antes = $turno->getAttributes();
        $this->agenda($turno, 'PENDIENTE');
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->assertDatabaseCount('ejecuciones_agenda_diaria', 1);
    }

    public function test_ei10_exportacion_conserva_referencia_y_consulta_refleja_cancelacion(): void
    {
        $turno = $this->turno();
        $this->preparar($turno);
        $antes = $turno->getAttributes();
        $fecha = $turno->inicio_at->toDateString();
        $this->interno();
        $generador = app(GenerarAgendaDiariaService::class);
        $primera = $generador->ejecutar($turno->inicio_at);
        $segunda = $generador->ejecutar($turno->inicio_at);
        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->assertDatabaseCount('jornadas_generacion_agenda', 1);
        $exportacion = $this->actingAs($turno->kinesiologa->user)
            ->get(route('turnos.agenda-atencion.imprimir', ['fecha' => $fecha]))
            ->assertOk()->assertSee('Pendiente')->assertDontSee('CLINICO-EI-NO-EXPONER');
        $snapshot = $exportacion->getContent();
        $descarga = $this->get(route('turnos.agenda-atencion.pdf', ['fecha' => $fecha]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $descarga->getContent();
        $this->assertTrue(str_starts_with($pdf, '%PDF-'));
        $this->assertStringContainsString('20261106_100000', $descarga->headers->get('Content-Disposition'));
        Auth::guard()->logout();
        $this->interno();
        $this->autorizar();
        $this->decidir('cancelar');
        $this->agenda($turno, 'CANCELADO');
        $this->assertSame($snapshot, $exportacion->getContent());
        $this->assertSame(hash('sha256', $pdf), hash('sha256', $descarga->getContent()));
        $this->assertTrue(str_contains($snapshot, 'Pendiente'));
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }
}
