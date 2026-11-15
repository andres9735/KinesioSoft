<?php

namespace Tests\Feature\Turnos;

use App\Enums\TipoTurnoEnum;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class EnlaceAsistenciaTurnoHttpTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    private string $url;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 07:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPOSICION',
        ])->fresh();
        $this->url = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id)->urlParaCorreo();
        parse_str(parse_url($this->url, PHP_URL_QUERY), $query);
        $this->token = $query['token'];
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_get_head_y_previsualizaciones_repetidas_son_lecturas_sin_sesion_o_credencial_en_respuesta(): void
    {
        $this->assertGuest();
        $antes = $this->turno->getAttributes();
        $enlaces = DB::table('enlaces_asistencia_turno')->get()->toJson();
        $revisiones = DB::table('revisiones_enlace_asistencia_turno')->get()->toJson();
        $escrituras = [];
        DB::listen(function ($query) use (&$escrituras): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)/i', $query->sql)) {
                $escrituras[] = $query->sql;
            }
        });
        for ($i = 0; $i < 3; $i++) {
            $response = $this->withHeaders(['User-Agent' => 'Mail preview scanner'])->get($this->url);
            $response->assertOk()->assertSee('Seguimiento')->assertSee('08/11/2026')->assertSee('10:00–10:30');
            $response->assertSee('Pendiente')->assertDontSee('CLINICO-NO-EXPOSICION');
            $response->assertDontSee($this->token, false)->assertDontSee('token_hash');
            $response->assertDontSee($this->turno->paciente->user->email);
            $response->assertDontSee($this->turno->paciente->user->dni);
            $this->assertFalse($this->app['request']->hasSession());
            $this->assertSame([], $this->app['request']->query());
            $this->assertStringNotContainsString($this->token, $this->app['request']->fullUrl());
            $this->assertStringNotContainsString($this->token, $this->app['request']->getRequestUri());
            $cookies = $response->headers->getCookies();
            $this->assertCount(3, $cookies);
            $this->assertSame(AutorizacionNavegadorEnlaceService::COOKIE, $cookies[0]->getName());
            $this->assertTrue($cookies[0]->isSecure() && $cookies[0]->isHttpOnly());
        }
        $this->head($this->url)->assertOk();
        $this->assertSame([], $escrituras);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame($enlaces, DB::table('enlaces_asistencia_turno')->get()->toJson());
        $this->assertSame($revisiones, DB::table('revisiones_enlace_asistencia_turno')->get()->toJson());
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_headers_y_documento_no_cargan_recursos_ni_reenvian_credencial(): void
    {
        $response = $this->get($this->url);
        $response->assertHeader('Referrer-Policy', 'strict-origin')->assertHeader('Pragma', 'no-cache');
        $response->assertSee('<meta name="referrer" content="strict-origin">', false);
        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("form-action 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertDoesNotMatchRegularExpression('/<(script|link|img|iframe)\b|\b(?:src|href|action)\s*=\s*["\x27]https?:/i', $response->getContent());
        $response->assertSee('lang="es"', false)->assertSee('name="viewport"', false);
        $response->assertSee('aria-labelledby="titulo"', false);
        $response->assertHeaderMissing('Location');
        $this->head($this->url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    #[DataProvider('rechazos')]
    public function test_rechazos_sin_datos_ni_escrituras(string $caso, int $status, string $mensaje): void
    {
        $url = $this->url;
        switch ($caso) {
            case 'ausente': $url = 'https://consultorio.example.test/turnos/asistencia?turno_id='.$this->turno->id;
                break;
            case 'desconocido': $url = 'https://consultorio.example.test/turnos/asistencia?token='.str_repeat('a', 64);
                break;
            case 'alterado': $url .= 'a';
                break;
            case 'array': $url = 'https://consultorio.example.test/turnos/asistencia?token[]=x';
                break;
            case 'vencido': CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08 10:00:00', config('app.timezone')));
                break;
            case 'revocado': EnlaceAsistenciaTurno::sole()->forceFill(['revocado_at' => CarbonImmutable::now(config('app.timezone'))])->save();
                break;
            case 'aba':
                DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-09 10:00:00']);
                DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00']);
                break;
            default: DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $caso]);
        }
        $antes = $this->turno->fresh()->getAttributes();
        $enlaceAntes = DB::table('enlaces_asistencia_turno')->get()->toJson();
        $response = $this->get($url);
        $response->assertStatus($status)->assertSee($mensaje);
        $response->assertDontSee('08/11/2026')->assertDontSee('CLINICO-NO-EXPOSICION')->assertDontSee($this->token, false);
        $response->assertDontSee($this->turno->kinesiologa->user->name);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertSee('<meta name="referrer" content="no-referrer">', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame($enlaceAntes, DB::table('enlaces_asistencia_turno')->get()->toJson());
        $this->assertDatabaseCount('auditorias', 1);
    }

    public static function rechazos(): array
    {
        return [
            ['ausente', 403, 'El enlace no es válido.'], ['desconocido', 403, 'El enlace no es válido.'],
            ['alterado', 403, 'El enlace no es válido.'], ['array', 403, 'El enlace no es válido.'],
            ['vencido', 410, 'El enlace está vencido.'], ['revocado', 410, 'El enlace fue revocado.'],
            ['aba', 409, 'programación anterior'], ['CANCELADO', 409, 'estado actual'],
            ['EN_ATENCION', 409, 'estado actual'], ['ATENDIDO', 409, 'estado actual'], ['AUSENTE', 409, 'estado actual'],
        ];
    }

    #[DataProvider('acciones')]
    public function test_acciones_presentadas_segun_estado_y_plazo_con_decision_expresa(string $estado, string $ahora, string $confirmacion, string $cancelacion): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse($ahora, config('app.timezone')));
        $response = $this->get($this->url);
        $response->assertOk()->assertSee($confirmacion)->assertSee($cancelacion)->assertDontSee($this->token, false);
        $politica = str_contains($response->getContent(), '<form ') ? 'strict-origin' : 'no-referrer';
        $response->assertHeader('Referrer-Policy', $politica);
        $response->assertSee('<meta name="referrer" content="'.$politica.'">', false);
        $this->assertSame($estado, $this->turno->fresh()->estado->value);
    }

    public static function acciones(): array
    {
        return [
            ['PENDIENTE', '2026-11-07 22:00:00', 'permitido antes del inicio', 'permitido mientras resten al menos 12 horas'],
            ['PENDIENTE', '2026-11-07 22:00:00.000001', 'permitido antes del inicio', 'no disponible online'],
            ['CONFIRMADO', '2026-11-07 22:00:00', 'ya está confirmado', 'permitido mientras resten al menos 12 horas'],
            ['CONFIRMADO', '2026-11-07 22:00:00.000001', 'ya está confirmado', 'Comunicate con el consultorio'],
        ];
    }

    public function test_identificadores_manipulados_no_amplian_el_acceso(): void
    {
        $otro = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'inicio_at' => '2026-11-20 15:00:00', 'fin_at' => '2026-11-20 15:30:00',
        ]);
        $response = $this->get($this->url.'&turno_id='.$otro->id.'&paciente_id='.$otro->paciente_id.'&kinesiologa_id='.$otro->kinesiologa_id.'&accion=cancelar');
        $response->assertOk()->assertSee('08/11/2026')->assertDontSee('20/11/2026');
        $response->assertDontSee($otro->kinesiologa->user->name);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
        $this->assertSame('PENDIENTE', $otro->fresh()->estado->value);
    }

    public function test_html_escapa_datos_y_no_incluye_datos_clinicos_o_del_portal(): void
    {
        $this->turno->kinesiologa->user->forceFill(['name' => '<script>alert(1)</script>'])->save();
        $response = $this->get($this->url);
        $response->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>', false);
        $response->assertDontSee('CLINICO-NO-EXPOSICION')->assertDontSee('documentos-clinicos')->assertDontSee('mis-turnos');
    }

    public function test_usuario_inactivo_autenticado_no_provoca_logout_ni_escrituras_del_portal(): void
    {
        $user = $this->turno->paciente->user;
        $user->forceFill(['activo' => false, 'remember_token' => 'conservar'])->save();
        $user->refresh();
        $antes = $user->getAttributes();
        $this->actingAs($user)->get($this->url)->assertOk();
        $this->assertSame($antes, $user->fresh()->getAttributes());
        $this->assertFalse($this->app['request']->hasSession());
    }

    public function test_error_tecnico_es_generico_sin_secretos_incluso_con_debug_y_referer_sensible(): void
    {
        config(['app.debug' => true]);
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_contains($sql, 'from "enlaces_asistencia_turno"')) {
                throw new RuntimeException('Secreto que no debe registrarse: '.$this->token);
            }
        });
        Log::shouldReceive('error')->once()->with('No se pudo consultar el enlace de asistencia.');
        $response = $this->withHeaders(['Referer' => $this->url])->get($this->url);
        $response->assertStatus(503)->assertSee('No pudimos consultar el enlace');
        $response->assertDontSee($this->token, false)->assertDontSee('RuntimeException')->assertDontSee('CLINICO-NO-EXPOSICION');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNull($this->app['request']->headers->get('referer'));
        $this->assertStringNotContainsString($this->token, $this->app['request']->getRequestUri());
    }

    public function test_http_no_redirige_ni_presenta_la_credencial(): void
    {
        $this->get(str_replace('https://', 'http://', $this->url))->assertStatus(400)
            ->assertHeaderMissing('Location')->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee($this->token, false);
    }
}
