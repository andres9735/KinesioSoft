<?php

namespace Tests\Feature\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Enums\RoleEnum;
use App\Http\Middleware\EnsureUserProfileIsComplete;
use App\Models\Auditoria;
use App\Models\IntentoRecordatorioTurno;
use App\Models\Turno;
use App\Services\Turnos\ConsultarAlertasRecordatorioService;
use App\Services\Turnos\DespacharRecordatorioTurnoService;
use App\Services\Turnos\GestionarReintentosRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\Support\CreaPerfilesUc07;
use Tests\Support\StreamSmtpRecordatorio;
use Tests\TestCase;

class AlertasRecordatorioTest extends TestCase
{
    use CreaPerfilesUc07;

    private Turno $turno;

    private int $recordatorio;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Se requiere SQLite en memoria sin PDO MySQL.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1025,
                'url' => null, 'scheme' => null, 'username' => null, 'password' => null],
            'mail.from.address' => 'consultorio@example.test', 'mail.from.name' => 'Consultorio']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'id' => 27026,
            'tipo' => 'PRIMERA_CONSULTA', 'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        $this->recordatorio = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar()[0];
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function transporte(bool $desconectado = false, Resultado $resultado = Resultado::FALLO_DEFINITIVO): void
    {
        $stream = new StreamSmtpRecordatorio;
        $stream->fallaInicio = $desconectado;
        if ($resultado === Resultado::FALLO_DEFINITIVO) {
            $stream->respuestas[4] = "550 RECHAZO-SINTETICO-NO-EXPONER\r\n";
        } elseif ($resultado === Resultado::INCIERTO) {
            $stream->respuestas[6] = '';
        }
        $this->instance(TransportarRecordatorioTurnoService::class, new class($stream) extends TransportarRecordatorioTurnoService
        {
            public function __construct(private StreamSmtpRecordatorio $stream) {}

            protected function crearTransporte(string $host, int $puerto): SmtpTransport
            {
                return new SmtpTransport($this->stream);
            }
        });
    }

    private function rechazar(): string
    {
        $this->transporte();
        $identidad = (string) Str::uuid();
        $this->assertSame(Resultado::FALLO_DEFINITIVO,
            app(DespacharRecordatorioTurnoService::class)->despachar($this->recordatorio, $identidad));

        return $identidad;
    }

    public function test_rechazo_smtp_genera_aviso_interno_a_administradores_activos_sin_cambiar_turno(): void
    {
        $administradores = [$this->usuarioUc07(RoleEnum::ADMINISTRADOR), $this->usuarioUc07(RoleEnum::ADMINISTRADOR)];
        $antes = $this->turno->getAttributes();
        $identidad = $this->rechazar();
        $alertas = app(ConsultarAlertasRecordatorioService::class);
        $auditCount = Auditoria::count();
        $this->assertSame(Resultado::FALLO_DEFINITIVO,
            app(DespacharRecordatorioTurnoService::class)->despachar($this->recordatorio, $identidad));
        $this->assertSame($auditCount, Auditoria::count());
        $this->assertSame(1, IntentoRecordatorioTurno::count());
        foreach ($administradores as $administrador) {
            $this->assertSame(1, $alertas->contar($administrador));
            $this->actingAs($administrador)->get(route('turnos.recordatorios.alertas'))
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertSee('Alertas registradas: 1')->assertSee('rechazó definitivamente')
                ->assertSee('Recordatorio #'.$this->recordatorio)
                ->assertSee('Turno #'.$this->turno->id)
                ->assertDontSee('CLINICO-NO-EXPONER')->assertDontSee('RECHAZO-SINTETICO-NO-EXPONER')
                ->assertDontSee($this->turno->paciente->user->email)->assertDontSee('?token=')
                ->assertDontSee('/turnos/asistencia/');
            $html = view('layouts.navigation')->render();
            $this->assertSame(2, substr_count($html, 'href="'.route('turnos.recordatorios.alertas').'"'));
            $this->assertSame(2, substr_count($html, 'aria-label="Alertas registradas: 1"'));
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $enlaces = (new \DOMXPath($dom))->query('//a[@href="'.route('turnos.recordatorios.alertas').'"]');
            foreach ($enlaces as $enlace) {
                $this->assertSame('page', $enlace->getAttribute('aria-current'));
            }
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame($auditCount, Auditoria::count());
        // La incidencia sigue visible aunque una evaluación posterior cambie el diagnóstico técnico.
        Auth::forgetGuards();
        request()->setRouteResolver(static fn () => null);
        CarbonImmutable::setTestNow($this->turno->inicio_at);
        app(GestionarReintentosRecordatorioTurnoService::class)->revisar($this->recordatorio);
        $this->assertSame(1, $alertas->contar($administradores[0]));
        $this->assertSame('TRANSPORTE_DEFINITIVO', $alertas->listar($administradores[0])->first()->valores_nuevos['incidencia']);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
    }

    #[DataProvider('agotamientos')]
    public function test_indisponibilidad_del_canal_se_alerta_al_agotar_tratamiento_sin_duplicar(bool $venceVentana): void
    {
        $administrador = $this->usuarioUc07(RoleEnum::ADMINISTRADOR);
        $antes = $this->turno->getAttributes();
        $this->transporte(true);
        $despacho = app(DespacharRecordatorioTurnoService::class);
        $gestion = app(GestionarReintentosRecordatorioTurnoService::class);
        $alertas = app(ConsultarAlertasRecordatorioService::class);
        $this->assertSame(Resultado::FALLO_RECUPERABLE, $despacho->despachar($this->recordatorio, (string) Str::uuid()));
        $this->assertSame(0, $alertas->contar($administrador));
        if ($venceVentana) {
            CarbonImmutable::setTestNow(CarbonImmutable::now()->addHour()->addSecond());
        } else {
            foreach ([5, 10, 20] as $minutos) {
                CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes($minutos));
                $plan = $gestion->revisar($this->recordatorio);
                $this->assertSame(Resultado::FALLO_RECUPERABLE,
                    $despacho->despachar($plan['recordatorio_id'], $plan['identidad'], $plan['numero']));
            }
        }
        $this->assertNull($gestion->revisar($this->recordatorio));
        $this->assertSame(1, $alertas->contar($administrador));
        $this->assertSame($venceVentana ? 'VENTANA_REINTENTOS_AGOTADA' : 'REINTENTOS_AGOTADOS',
            $alertas->listar($administrador)->sole()->valores_nuevos['incidencia']);
        $gestion->revisar($this->recordatorio);
        $this->assertSame(1, $alertas->contar($administrador));
        $this->actingAs($administrador)->get(route('turnos.recordatorios.alertas'))
            ->assertOk()->assertSee('Alertas registradas: 1')
            ->assertSee('Turno #'.$this->turno->id)->assertSee('Recordatorio #'.$this->recordatorio)
            ->assertSee(ConsultarAlertasRecordatorioService::MENSAJES[$venceVentana ? 'VENTANA_REINTENTOS_AGOTADA' : 'REINTENTOS_AGOTADOS'])
            ->assertDontSee('CLINICO-NO-EXPONER')->assertDontSee('/turnos/asistencia/');
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
    }

    public static function agotamientos(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('resultadosNoDefinitivos')]
    public function test_aceptacion_e_incertidumbre_no_se_presentan_como_fallo_definitivo(Resultado $resultado): void
    {
        $administrador = $this->usuarioUc07(RoleEnum::ADMINISTRADOR);
        $antes = $this->turno->getAttributes();
        $this->transporte(false, $resultado);
        $this->assertSame($resultado, app(DespacharRecordatorioTurnoService::class)->despachar($this->recordatorio, (string) Str::uuid()));
        $this->assertSame(0, app(ConsultarAlertasRecordatorioService::class)->contar($administrador));
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
    }

    public static function resultadosNoDefinitivos(): array
    {
        return [[Resultado::ACEPTADO], [Resultado::INCIERTO]];
    }

    #[DataProvider('rolesAjenos')]
    public function test_ningun_otro_rol_ni_administrador_inactivo_recibe_avisos(RoleEnum $rol, bool $activo): void
    {
        $usuario = $this->usuarioUc07($rol);
        $usuario->update(['activo' => $activo]);
        $this->rechazar();
        $this->actingAs($usuario);
        $this->assertStringNotContainsString('Alertas de recordatorios', view('layouts.navigation')->render());
        $this->withoutMiddleware(EnsureUserProfileIsComplete::class)->get(route('turnos.recordatorios.alertas'))
            ->assertStatus($activo ? 403 : 302)->assertDontSee('rechazó definitivamente');
        $this->expectException(HttpException::class);
        app(ConsultarAlertasRecordatorioService::class)->listar($usuario);
    }

    public static function rolesAjenos(): array
    {
        return [[RoleEnum::SUPERADMIN, true], [RoleEnum::KINESIOLOGO, true],
            [RoleEnum::PACIENTE, true], [RoleEnum::ADMINISTRADOR, false]];
    }

    public function test_sin_alertas_muestra_vacio_y_guest_no_puede_consultarlas(): void
    {
        $this->get(route('turnos.recordatorios.alertas'))->assertRedirect(route('login'));
        $this->actingAs($this->usuarioUc07(RoleEnum::ADMINISTRADOR))->get(route('turnos.recordatorios.alertas'))
            ->assertOk()->assertSee('No hay alertas de envío registradas.');
    }
}
