<?php

namespace Tests\Feature\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\Agenda\AgendaAtencionQueryService;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\MisTurnosQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class ResolverAsistenciaEnlaceHttpTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    private string $url;

    private string $token;

    private string $csrf;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        $this->ahora('2026-11-06 07:00:00');
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
            'motivo_solicitud' => 'CLINICO-SECRETO',
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

    public function test_get_acredita_sin_login_cookie_sin_original_y_formularios_con_decision_explicita(): void
    {
        $this->assertGuest();
        $response = $this->autorizar();
        $response->assertSee('required', false)->assertSee('Revisá y confirmá tu decisión');
        $response->assertDontSee($this->token, false)->assertDontSee('CLINICO-SECRETO');
        $cookie = $response->headers->getCookies()[0];
        $this->assertTrue($cookie->isSecure() && $cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertStringNotContainsString($this->token, Crypt::decryptString($cookie->getValue()));
        $this->assertFalse($this->app['request']->hasSession());
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
    }

    public function test_confirmar_repetir_y_cancelar_con_misma_cookie_y_resultado_get_sin_reescritura(): void
    {
        $this->autorizar();
        $this->resultado($this->decidir('confirmar'))->assertSee('Tu asistencia quedó confirmada.');
        $this->assertSame('CONFIRMADO', $this->turno->fresh()->estado->value);
        $this->resultado($this->decidir('confirmar'))->assertSee('ya estaba registrada');
        $this->assertDatabaseCount('auditorias', 3);
        $this->resultado($this->decidir('cancelar'))->assertSee('El turno quedó cancelado.');
        $this->assertSame('CANCELADO', $this->turno->fresh()->estado->value);
        for ($i = 0; $i < 3; $i++) {
            $this->get('https://consultorio.example.test/turnos/asistencia/resultado')->assertOk()->assertSee('El turno quedó cancelado.');
        }
        $this->resultado($this->decidir('cancelar'))->assertSee('ya estaba registrada');
        $this->assertDatabaseCount('auditorias', 5);
        $this->assertSame(2, Auditoria::where('evento', 'enlace_asistencia_resultado')->count());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $auditoria = Auditoria::where('evento', 'turno_cancelado')->sole();
        $this->assertSame('ENLACE_SEGURO', $auditoria->valores_nuevos['canal']);
        $this->assertSame('Navegador UC28', $auditoria->user_agent);
        $this->assertStringNotContainsString($this->token, $auditoria->toJson());
        $this->assertStringNotContainsString($this->csrf, $auditoria->toJson());
    }

    #[DataProvider('limitesCancelacion')]
    public function test_cancelar_pendiente_o_confirmado_en_limite_o_fuera(string $estado, string $fecha, bool $permitido): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado]);
        $this->ahora('2026-11-07 22:00:00');
        $this->autorizar();
        $this->ahora($fecha);
        $antes = $this->turno->fresh()->getAttributes();
        $response = $this->resultado($this->decidir('cancelar'));
        $response->assertSee($permitido ? 'El turno quedó cancelado.' : 'Restan menos de 12 horas');
        $this->assertSame($permitido ? 'CANCELADO' : $estado, $this->turno->fresh()->estado->value);
        if (! $permitido) {
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public static function limitesCancelacion(): array
    {
        return [['PENDIENTE', '2026-11-07 22:00:00', true], ['CONFIRMADO', '2026-11-07 22:00:00', true], ['PENDIENTE', '2026-11-07 22:00:00.000001', false], ['CONFIRMADO', '2026-11-07 22:00:00.000001', false]];
    }

    #[DataProvider('rechazosCsrf')]
    public function test_csrf_no_se_omite_en_testing(string $caso): void
    {
        $this->autorizar();
        $headers = ['Origin' => 'https://consultorio.example.test', 'Sec-Fetch-Site' => 'same-origin'];
        $csrf = $this->csrf;
        if ($caso === 'token') {
            $csrf = 'invalido';
        } elseif ($caso === 'origen') {
            $headers['Origin'] = 'https://atacante.example.test';
        } elseif ($caso === 'sin_origen') {
            $headers['Origin'] = '';
        } elseif ($caso === 'origen_nulo') {
            $headers['Origin'] = 'null';
        } else {
            $headers['Sec-Fetch-Site'] = 'cross-site';
        }
        $this->withHeaders($headers)->post('https://consultorio.example.test/turnos/asistencia/decidir', ['_csrf_enlace' => $csrf, 'accion' => 'cancelar', 'acepto' => '1'])->assertStatus(419);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public static function rechazosCsrf(): array
    {
        return [['token'], ['origen'], ['sin_origen'], ['origen_nulo'], ['cross_site']];
    }

    #[DataProvider('origenesConPuerto')]
    public function test_formulario_https_con_puerto_exige_origen_exacto(string $origen, int $status): void
    {
        config(['app.url' => 'https://localhost:8443']);
        $this->url = str_replace('https://consultorio.example.test', 'https://localhost:8443', $this->url);
        $this->autorizar()->assertHeader('Referrer-Policy', 'strict-origin')
            ->assertSee('<meta name="referrer" content="strict-origin">', false);

        // strict-origin transmite sólo el origen; la credencial no viaja en Referer.
        $this->withHeaders(['Origin' => $origen, 'Sec-Fetch-Site' => 'same-origin', 'Referer' => 'https://localhost:8443/'])
            ->post('https://localhost:8443/turnos/asistencia/decidir', [
                '_csrf_enlace' => $this->csrf, 'accion' => 'confirmar', 'acepto' => '1',
            ])->assertStatus($status)->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertDontSee($this->token, false);

        $this->assertNull($this->app['request']->headers->get('Referer'));
        $this->assertSame($status === 303 ? 'CONFIRMADO' : 'PENDIENTE', $this->turno->fresh()->estado->value);
        $this->assertSame($status === 303 ? 1 : 0, Auditoria::where('evento', 'turno_asistencia_confirmada')->count());
        $this->assertStringNotContainsString($this->token, Auditoria::all()->toJson());
    }

    public static function origenesConPuerto(): array
    {
        return [
            'exacto' => ['https://localhost:8443', 303],
            'sin_puerto' => ['https://localhost', 419],
            'otro_puerto' => ['https://localhost:8444', 419],
            'nulo' => ['null', 419],
            'ajeno' => ['https://ajeno.example.test:8443', 419],
        ];
    }

    public function test_post_sin_cookie_o_solo_con_token_original_no_autoriza(): void
    {
        $this->post('https://consultorio.example.test/turnos/asistencia/decidir?token='.$this->token, ['accion' => 'confirmar', 'acepto' => '1'])->assertStatus(403);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_cookie_manipulada_no_autoriza(): void
    {
        $this->autorizar();
        $this->withUnencryptedCookie(AutorizacionNavegadorEnlaceService::COOKIE, 'alterada');
        $this->decidir('confirmar')->assertStatus(403);
        $this->assertDatabaseCount('auditorias', 1);
    }

    #[DataProvider('cambiosPosteriores')]
    public function test_cambios_entre_get_y_post_prevalecen(string $caso, string $mensaje): void
    {
        $this->autorizar();
        switch ($caso) {
            case 'revocado': EnlaceAsistenciaTurno::sole()->forceFill(['revocado_at' => CarbonImmutable::now(config('app.timezone'))])->save();
                break;
            case 'aba':
                DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-09 10:00:00']);
                DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00']);
                break;
            default: DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $caso]);
        }
        $antes = $this->turno->fresh()->getAttributes();
        $this->resultado($this->decidir('confirmar'))->assertSee($mensaje);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 1);
    }

    public static function cambiosPosteriores(): array
    {
        return [['revocado', 'fue revocado'], ['aba', 'programación o autorización cambió'], ['CANCELADO', 'no permite nuevas operaciones'], ['EN_ATENCION', 'no permite nuevas operaciones']];
    }

    public function test_cookie_expira_y_enlace_original_puede_renovarla_sin_cambiar_turno(): void
    {
        $this->autorizar();
        $this->ahora('2026-11-06 07:15:00');
        $this->decidir('confirmar')->assertStatus(410)->assertSee('venció');
        $this->autorizar();
        $this->resultado($this->decidir('confirmar'))->assertSee('quedó confirmada');
    }

    public function test_turno_alcanza_inicio_y_no_hay_operacion(): void
    {
        $this->ahora('2026-11-08 09:59:59');
        $this->autorizar();
        $this->ahora('2026-11-08 10:00:00');
        $this->decidir('confirmar')->assertStatus(410);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
    }

    public function test_no_anticipa_el_inicio_con_microsegundos_por_precision_de_la_cookie(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00.500000']);
        $this->url = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id)->urlParaCorreo();
        $this->ahora('2026-11-08 10:00:00.100000');
        $this->autorizar();
        $this->resultado($this->decidir('confirmar'))->assertSee('quedó confirmada');
    }

    public function test_formulario_de_otra_pestana_no_decide_sobre_el_enlace_actual(): void
    {
        $this->autorizar();
        $otro = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'inicio_at' => '2026-11-08 11:00:00', 'fin_at' => '2026-11-08 11:30:00',
        ]);
        $url = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($otro->id)->urlParaCorreo();
        $response = $this->get($url)->assertOk();
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
        $this->decidir('cancelar')->assertStatus(419);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
        $this->assertSame('PENDIENTE', $otro->fresh()->estado->value);
    }

    public function test_sin_decision_expresa_e_identificadores_ajenos_no_alteran_turnos(): void
    {
        $this->autorizar();
        $this->resultado($this->decidir('cancelar', ['acepto' => '0']))->assertSee('confirmá expresamente');
        $otro = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create();
        $this->resultado($this->decidir('confirmar', ['turno_id' => $otro->id, 'paciente_id' => $otro->paciente_id, 'canal' => 'MIS_TURNOS']))->assertSee('quedó confirmada');
        $this->assertSame('PENDIENTE', $otro->fresh()->estado->value);
        $this->assertSame('CONFIRMADO', $this->turno->fresh()->estado->value);
    }

    public function test_error_recuperable_rollback_y_resultado_no_exponen_secretos(): void
    {
        $this->autorizar();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'insert into "auditorias"')) {
                throw new RuntimeException('No divulgar '.$this->token);
            }
        });
        Log::shouldReceive('error')->once()->with('No se pudo resolver la asistencia mediante enlace.');
        $response = $this->resultado($this->decidir('cancelar'));
        $response->assertSee('No pudimos comprobar el resultado')->assertDontSee($this->token, false)->assertDontSee('RuntimeException');
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_auditoria_cookies_no_escalares_no_evitan_saneamiento_ni_autorizan_post(): void
    {
        config(['app.debug' => true]);
        $cookies = [AutorizacionNavegadorEnlaceService::COOKIE => ['dato' => $this->token]];
        $response = $this->call('GET', $this->url, [], $cookies);
        $response->assertOk()->assertDontSee($this->token, false)->assertHeader('Referrer-Policy', 'strict-origin');
        $this->assertSame('', $this->app['request']->server->get('QUERY_STRING'));
        $this->call('POST', 'https://consultorio.example.test/turnos/asistencia/decidir?token='.$this->token, [], $cookies)
            ->assertStatus(403)->assertDontSee($this->token, false);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
    }

    public function test_auditoria_recibo_sustituido_por_cookie_de_autorizacion_es_invalido(): void
    {
        $this->autorizar();
        $recibo = app(AutorizacionNavegadorEnlaceService::class)->resultado(EnlaceAsistenciaTurno::sole()->id, ['accion' => 'confirmar', 'replay' => false]);
        $this->withUnencryptedCookie(AutorizacionNavegadorEnlaceService::COOKIE, $recibo->getValue());
        $this->decidir('confirmar')->assertStatus(403)->assertSee('no es válido');
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_auditoria_host_ajeno_no_recibe_cookie_ni_datos_de_cita(): void
    {
        $this->get(str_replace('consultorio.example.test', 'ajeno.example.test', $this->url))
            ->assertStatus(400)->assertDontSee('Seguimiento')->assertDontSee($this->token, false);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_auditoria_mac_impide_sustituir_payload_aun_con_formato_cifrado_valido(): void
    {
        $response = $this->autorizar();
        $cifrado = json_decode(base64_decode($response->headers->getCookies()[0]->getValue()), true, flags: JSON_THROW_ON_ERROR);
        $cifrado['mac'][0] = $cifrado['mac'][0] === 'a' ? 'b' : 'a';
        $this->withUnencryptedCookie(AutorizacionNavegadorEnlaceService::COOKIE, base64_encode(json_encode($cifrado, JSON_THROW_ON_ERROR)));
        $this->decidir('cancelar')->assertStatus(403);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_auditoria_limite_de_frecuencia_no_duplica_transiciones(): void
    {
        $this->autorizar();
        for ($i = 0; $i < 10; $i++) {
            $this->decidir('confirmar')->assertStatus(303);
        }
        $this->decidir('cancelar')->assertStatus(429)->assertSee('demasiadas solicitudes');
        $this->assertSame('CONFIRMADO', $this->turno->fresh()->estado->value);
        $this->assertDatabaseCount('auditorias', 3);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_auditoria_head_y_consulta_con_cookie_no_renuevan_autorizacion(): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $head = $this->head($this->url)->assertOk();
        $this->assertSame([], $head->headers->getCookies());
        $response = $this->autorizar();
        $datos = app(AutorizacionNavegadorEnlaceService::class)->leer($response->headers->getCookies()[0]->getValue());
        $this->assertSame(CarbonImmutable::now(config('app.timezone'))->addMinutes(15)->timestamp, $datos['exp']);
        $this->ahora('2026-11-06 07:14:59');
        $consulta = $this->get('https://consultorio.example.test/turnos/asistencia')->assertOk();
        $this->assertSame([], $consulta->headers->getCookies());
        $this->ahora('2026-11-06 07:15:00');
        $this->get('https://consultorio.example.test/turnos/asistencia')->assertStatus(410);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_auditoria_cambios_por_enlace_se_reflejan_en_lecturas_uc06_uc05_y_uc29(): void
    {
        $this->autorizar();
        $paciente = $this->turno->paciente;
        $profesional = $this->turno->kinesiologa;
        $fecha = CarbonImmutable::parse('2026-11-08', config('app.timezone'));
        $agenda = app(AgendaAtencionQueryService::class);
        $misTurnos = app(MisTurnosQueryService::class);
        $this->decidir('confirmar')->assertStatus(303);
        $this->assertSame('CONFIRMADO', $misTurnos->listar($paciente, [], [])['proximos']->sole()->estado->value);
        $this->assertSame('CONFIRMADO', $agenda->informacionDePeriodo($profesional, $fecha, $fecha)[0]['estado']->value);
        $this->decidir('cancelar')->assertStatus(303);
        $listado = $misTurnos->listar($paciente, [], []);
        $this->assertSame(0, $listado['proximos']->total());
        $this->assertSame('CANCELADO', $listado['historicos']->sole()->estado->value);
        $this->assertSame('CANCELADO', $agenda->informacionDePeriodo($profesional, $fecha, $fecha)[0]['estado']->value);
        $this->assertDatabaseCount('auditorias', 5);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    #[DataProvider('decisionesRecuperables')]
    public function test_recuperacion_respuesta_perdida_correlacion_previa_y_repeticion(string $accion, string $estado): void
    {
        $response = $this->autorizar();
        $operaciones = $this->operaciones($response);
        $operacion = $operaciones[$accion === 'confirmar' ? 0 : 1];
        $this->consultarResultado($operacion)->assertSee('No hay un resultado consolidado');
        $antes = $this->turno->fresh()->getAttributes();
        // Descartar por completo la respuesta POST: no incorporar su cookie de resultado.
        $this->decidir($accion)->assertStatus(303);
        $this->assertSame($estado, $this->turno->fresh()->estado->value);
        $this->withUnencryptedCookie(AutorizacionNavegadorEnlaceService::COOKIE, '');
        for ($i = 0; $i < 2; $i++) {
            $this->consultarResultado($operacion)->assertSee('ya estaba registrada')->assertDontSee($this->token, false)->assertDontSee('CLINICO-SECRETO');
        }
        $this->decidir($accion)->assertStatus(403); // La autorización de lectura no decide.
        $this->assertDatabaseCount('auditorias', 3);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', $accion === 'cancelar' ? 1 : 0);
        $registro = Auditoria::where('evento', 'enlace_asistencia_resultado')->sole();
        $this->assertSame(hash('sha256', $operacion), $registro->valores_nuevos['correlacion_hash']);
        $this->assertFalse($registro->valores_nuevos['replay_negocio']);
        $this->assertNotNull($registro->valores_nuevos['auditoria_transicion_id']);
        $this->assertStringNotContainsString($operacion, $registro->toJson());
        $this->assertStringNotContainsString($this->token, $registro->toJson());
        $this->assertSame(array_diff_key($antes, ['estado' => true, 'updated_at' => true]), array_diff_key($this->turno->fresh()->getAttributes(), ['estado' => true, 'updated_at' => true]));
    }

    public static function decisionesRecuperables(): array
    {
        return [['confirmar', 'CONFIRMADO'], ['cancelar', 'CANCELADO']];
    }

    public function test_recuperacion_sin_cookie_alterada_o_de_otro_navegador_se_rechaza(): void
    {
        $response = $this->autorizar();
        $operacion = $this->operaciones($response)[1];
        $nombre = AutorizacionNavegadorEnlaceService::nombreRecuperacion($operacion);
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === $nombre);
        $this->assertTrue($cookie->isSecure() && $cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/turnos/asistencia/resultado', $cookie->getPath());
        $this->assertStringNotContainsString($this->token, Crypt::decryptString($cookie->getValue()));
        $this->decidir('cancelar')->assertStatus(303);
        $this->withUnencryptedCookie($nombre, '');
        $this->consultarResultado($operacion)->assertStatus(403);
        $this->withUnencryptedCookie($nombre, 'alterada');
        $this->consultarResultado($operacion)->assertStatus(403);
        // Conocer el enlace original en otro navegador no reproduce su correlación.
        $this->withUnencryptedCookie($nombre, '');
        $this->get($this->url)->assertStatus(409);
        $this->consultarResultado($operacion)->assertStatus(403);
        $this->assertDatabaseCount('auditorias', 3);
    }

    public function test_recuperacion_expira_sin_renovarse_por_consulta(): void
    {
        $operacion = $this->operaciones($this->autorizar())[1];
        $this->decidir('cancelar')->assertStatus(303);
        $this->ahora('2026-11-06 07:14:59');
        $response = $this->consultarResultado($operacion)->assertSee('ya estaba registrada');
        $this->assertSame([], $response->headers->getCookies());
        $this->ahora('2026-11-06 07:15:00');
        $this->consultarResultado($operacion)->assertStatus(410)->assertDontSee('ya estaba registrada');
        $this->assertDatabaseCount('auditorias', 3);
    }

    public function test_recuperacion_dos_operaciones_y_pestanas_conservan_resultados_independientes(): void
    {
        $primera = $this->operaciones($this->autorizar());
        $this->decidir('confirmar')->assertStatus(303);
        $segunda = $this->operaciones($this->autorizar());
        $this->assertNotSame($primera, $segunda);
        $this->decidir('cancelar')->assertStatus(303);
        $this->consultarResultado($primera[0])->assertSee('ya estaba registrada');
        $this->consultarResultado($primera[1])->assertSee('No hay un resultado consolidado');
        $this->consultarResultado($segunda[0])->assertSee('No hay un resultado consolidado');
        $this->consultarResultado($segunda[1])->assertSee('ya estaba registrada');
        $this->assertDatabaseCount('auditorias', 5);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public function test_recuperacion_fallo_del_comprobante_revierte_transicion_auditoria_e_intencion(): void
    {
        $operacion = $this->operaciones($this->autorizar())[1];
        $antes = $this->turno->fresh()->getAttributes();
        DB::connection()->beforeExecuting(function (string $sql, array $bindings): void {
            if (str_starts_with($sql, 'insert into "auditorias"') && in_array('enlace_asistencia_resultado', $bindings, true)) {
                throw new RuntimeException('No divulgar '.$this->token);
            }
        });
        Log::shouldReceive('error')->once()->with('No se pudo resolver la asistencia mediante enlace.');
        $this->resultado($this->decidir('cancelar'))->assertSee('No pudimos comprobar el resultado');
        $this->consultarResultado($operacion)->assertSee('No hay un resultado consolidado')->assertDontSee('ya estaba registrada');
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_recuperacion_no_atribuye_cancelacion_de_otro_canal_a_esta_operacion(): void
    {
        $operacion = $this->operaciones($this->autorizar())[1];
        app(CancelarTurnoAction::class)->ejecutar($this->turno->paciente->user, $this->turno);
        $this->consultarResultado($operacion)->assertSee('No hay un resultado consolidado')->assertDontSee('ya estaba registrada');
        $this->resultado($this->decidir('cancelar'))->assertSee('no permite nuevas operaciones');
        $this->assertSame('CANCELADO', $this->turno->fresh()->estado->value);
        $this->assertSame(0, Auditoria::where('evento', 'enlace_asistencia_resultado')->count());
        $this->assertDatabaseCount('auditorias', 2);
    }

    public function test_recuperacion_verifica_replay_de_negocio_sin_inventar_otra_transicion(): void
    {
        $operacion = $this->operaciones($this->autorizar())[0];
        app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($this->turno->paciente->user, $this->turno);
        $this->decidir('confirmar')->assertStatus(303);
        $this->consultarResultado($operacion)->assertSee('ya estaba registrada');
        $this->decidir('confirmar')->assertStatus(303);
        $registro = Auditoria::where('evento', 'enlace_asistencia_resultado')->sole();
        $this->assertTrue($registro->valores_nuevos['replay_negocio']);
        $this->assertNull($registro->valores_nuevos['auditoria_transicion_id']);
        $this->assertSame(1, Auditoria::where('evento', 'turno_asistencia_confirmada')->count());
        $this->assertDatabaseCount('auditorias', 3);
    }

    private function operaciones($response): array
    {
        preg_match_all('/resultado\?operacion=([0-9a-f-]{36})/', $response->getContent(), $matches);
        $this->assertCount(2, $matches[1]);

        return $matches[1];
    }

    private function consultarResultado(string $operacion)
    {
        $response = $this->get('https://consultorio.example.test/turnos/asistencia/resultado?operacion='.$operacion);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        return $response;
    }

    private function autorizar()
    {
        $response = $this->get($this->url)->assertOk();
        preg_match('/name="_csrf_enlace" value="([a-f0-9]+)"/', $response->getContent(), $match);
        $this->assertNotEmpty($match[1] ?? null);
        $this->csrf = $match[1];
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        return $response;
    }

    private function decidir(string $accion, array $extra = [])
    {
        return $this->withHeaders(['Origin' => 'https://consultorio.example.test', 'Sec-Fetch-Site' => 'same-origin', 'User-Agent' => 'Navegador UC28'])->post('https://consultorio.example.test/turnos/asistencia/decidir', array_merge(['_csrf_enlace' => $this->csrf, 'accion' => $accion, 'acepto' => '1'], $extra));
    }

    private function resultado($response)
    {
        $response->assertStatus(303)->assertHeader('Location', '/turnos/asistencia/resultado');
        $this->assertStringNotContainsString($this->token, $response->headers->get('Location'));
        $cookie = $response->headers->getCookies()[0];
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        $result = $this->get('https://consultorio.example.test/turnos/asistencia/resultado')->assertOk();
        $result->assertDontSee($this->token, false)->assertDontSee('CLINICO-SECRETO');
        $result->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));

        return $result;
    }

    private function ahora(string $fecha): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($fecha, config('app.timezone')));
    }
}
