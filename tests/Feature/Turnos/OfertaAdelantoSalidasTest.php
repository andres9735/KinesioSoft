<?php

namespace Tests\Feature\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Http\Middleware\ProtegerAccesoOfertaAdelanto;
use App\Jobs\DespacharOfertaAdelantoJob;
use App\Jobs\RevisarCicloOfertaAdelantoJob;
use App\Mail\OfertaAdelantoMail;
use App\Models\Auditoria;
use App\Models\DespachoOfertaAdelanto;
use App\Models\EvaluacionAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\IntentoDespachoAdelanto;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Turnos\CerrarOfertaAdelantoService;
use App\Services\Turnos\ContinuarCupoAdelantoService;
use App\Services\Turnos\DespacharOfertaAdelantoService;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use App\Services\Turnos\ProcesarIntencionCupoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\Support\SimularEnvioOfertaAdelanto;
use Tests\TestCase;

class OfertaAdelantoSalidasTest extends TestCase
{
    use CreaPerfilesUc07;
    use DatabaseMigrations;

    private Kinesiologa $profesional;

    private Turno $primero;

    private Turno $segundo;

    private IntencionEvaluacionCupo $intencion;

    private OfertaAdelanto $oferta;

    private DespachoOfertaAdelanto $despacho;

    private string $url;

    private string $token;

    private array $autorizacion;

    private string $cookie;

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
        config(['app.url' => 'https://consultorio.example.test']);
        $this->ahora('2026-11-15 10:00:00');
        Mail::fake();
        Queue::fake();
        $this->profesional = $this->profesionalUc07();
        $this->primero = $this->candidato('2026-11-23 10:00:00');
        $this->segundo = $this->candidato('2026-11-22 10:00:00');
        $paciente = $this->pacienteUc07();
        $cancelado = Turno::factory()->for($this->profesional)->for($paciente)->create([
            'tipo' => 'SEGUIMIENTO', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-16 10:00:00', 'fin_at' => '2026-11-16 10:45:00',
        ])->fresh();
        app(CancelarTurnoAction::class)->ejecutar($paciente->user, $cancelado);
        $this->intencion = IntencionEvaluacionCupo::query()->sole();
        $this->preparar('2026-11-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.Auditoria::class);
        Event::forget('eloquent.creating: '.DespachoOfertaAdelanto::class);
        DB::disableQueryLog();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_enlace_del_correo_llega_a_pantalla_sin_escrituras_ni_datos_clinicos(): void
    {
        $this->enviar();
        Mail::assertSent(OfertaAdelantoMail::class, function (OfertaAdelantoMail $mail): bool {
            preg_match('/href="([^"]+)"/', $mail->render(), $enlace);
            $this->assertSame($this->url, html_entity_decode($enlace[1]));

            return true;
        });
        $antes = $this->estadoPersistido();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $respuesta = $this->get($this->url)->assertOk()->assertSee('Rechazar oferta')->assertSee('Aceptar adelanto')
            ->assertSee('23/11/2026 10:00:00')->assertSee('16/11/2026 10:00:00')->assertSee('10:30:00')
            ->assertSee('14:00:00.000000')->assertDontSee('MOTIVO PRIVADO')->assertDontSee($this->token, false);
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringStartsWith('select ', strtolower(ltrim($query['query'])));
        }
        DB::disableQueryLog();
        $this->assertSame($antes, $this->estadoPersistido());
        $this->assertFalse($this->app['request']->hasSession());
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertSame('DENY', $respuesta->headers->get('X-Frame-Options'));
        $this->assertSame('', $this->app['request']->getQueryString() ?? '');
        $cookie = $respuesta->headers->getCookies()[0];
        $this->assertTrue($cookie->isSecure() && $cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertStringNotContainsString($this->token, Crypt::decryptString($cookie->getValue()));
    }

    #[DataProvider('credencialesInvalidas')]
    public function test_credencial_invalida_bloquea_todo_detalle(mixed $token): void
    {
        $url = 'https://consultorio.example.test/turnos/adelanto'.($token === null ? '' : '?'.http_build_query(['token' => $token]));
        $this->get($url)->assertForbidden()->assertDontSee('Horario original')->assertDontSee($this->primero->paciente->user->email);
        $this->assertSame(OfertaAdelanto::ACTIVA, $this->oferta->fresh()->estado);
    }

    public static function credencialesInvalidas(): array
    {
        return [[null], ['x'], [str_repeat('a', 64)], [str_repeat('A', 64)], [['hostil']], [str_repeat('1', 10000)]];
    }

    public function test_head_no_modifica_registros_ni_emite_cookie_de_respuesta(): void
    {
        $antes = $this->estadoPersistido();
        $respuesta = $this->head($this->url)->assertOk();
        $this->assertSame([], $respuesta->headers->getCookies());
        $this->assertSame($antes, $this->estadoPersistido());
    }

    public function test_rechazo_explicito_repetido_conserva_turno_y_registra_una_salida(): void
    {
        $antes = $this->primero->getAttributes();
        $this->autorizar();
        $this->rechazarHttp()->assertOk()->assertSee('La oferta quedó rechazada');
        $this->rechazarHttp()->assertOk()->assertSee('La oferta quedó rechazada');
        $this->assertSame(OfertaAdelanto::RECHAZADA, $this->oferta->fresh()->estado);
        $this->assertSame($antes, $this->primero->fresh()->getAttributes());
        $this->assertDatabaseCount('salidas_oferta_adelanto', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->assertSame(1, Auditoria::query()->where('evento', 'oferta_adelanto_salida_registrada')->count());
    }

    #[DataProvider('ataquesPost')]
    public function test_post_exige_cookie_csrf_origen_y_accion_correctos(string $caso, int $status): void
    {
        $this->autorizar();
        $datos = ['_csrf_oferta' => $this->autorizacion['csrf'], 'accion' => 'rechazar'];
        $cabeceras = ['Origin' => 'https://consultorio.example.test'];
        if ($caso === 'csrf') {
            $datos['_csrf_oferta'] = 'otro';
        } elseif ($caso === 'accion') {
            $datos['accion'] = 'aceptar';
        } elseif ($caso === 'origen') {
            $cabeceras['Origin'] = 'https://otro.example.test';
        } elseif ($caso === 'cross-site') {
            $cabeceras['Sec-Fetch-Site'] = 'cross-site';
        } else {
            $this->cookie = 'no es una autorización';
        }
        $this->withUnencryptedCookies([ProtegerAccesoOfertaAdelanto::COOKIE => $this->cookie])
            ->post('https://consultorio.example.test/turnos/adelanto/rechazar', $datos, $cabeceras)->assertStatus($status);
        $this->assertDatabaseCount('salidas_oferta_adelanto', 0);
    }

    public static function ataquesPost(): array
    {
        return [['csrf', 419], ['accion', 419], ['origen', 419], ['cross-site', 419], ['cookie', 403]];
    }

    public function test_origen_http_y_host_incorrecto_no_admiten_la_credencial(): void
    {
        $this->get(str_replace('https:', 'http:', $this->url))->assertStatus(400);
        $this->get(str_replace('consultorio.example.test', 'otro.example.test', $this->url))->assertStatus(400);
    }

    public function test_vencimiento_exacto_en_get_no_escribe_y_post_no_registra_rechazo(): void
    {
        $this->ahora('2026-11-15 13:59:59');
        $this->autorizar();
        CarbonImmutable::setTestNow($this->oferta->vence_at);
        $this->get($this->url)->assertStatus(410)->assertSee('La oferta venció');
        $this->assertSame(OfertaAdelanto::ACTIVA, $this->oferta->fresh()->estado);
        $this->rechazarHttp()->assertStatus(410)->assertSee('La oferta venció');
        $this->assertSame(OfertaAdelanto::VENCIDA, SalidaOfertaAdelanto::query()->sole()->resultado);
    }

    public function test_vencimiento_sin_abrir_cierra_y_continua_una_sola_vez(): void
    {
        CarbonImmutable::setTestNow($this->oferta->vence_at);
        $this->artisan('turnos:procesar-cupos')->assertSuccessful();
        Queue::assertPushed(RevisarCicloOfertaAdelantoJob::class, fn ($job): bool => $job->ofertaId === $this->oferta->id);
        $this->ciclo();
        $this->ciclo();
        $this->assertSame(OfertaAdelanto::VENCIDA, $this->oferta->fresh()->estado);
        $this->assertSame($this->segundo->id, OfertaAdelanto::query()->orderByDesc('id')->first()->turno_id);
        $this->assertDatabaseCount('ofertas_adelanto', 2);
        $this->assertDatabaseCount('evaluaciones_adelanto', 1);
        $this->assertDatabaseCount('salidas_oferta_adelanto', 1);
    }

    public function test_prioridad_y_exclusion_por_paciente_de_todos_sus_turnos(): void
    {
        Turno::factory()->for($this->profesional)->for($this->primero->paciente)->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'PENDIENTE',
            'inicio_at' => '2026-11-23 09:00:00', 'fin_at' => '2026-11-23 09:30:00',
        ]);
        $this->candidato('2026-11-21 10:00:00');
        $this->cerrar();
        $this->ciclo();
        $siguiente = OfertaAdelanto::query()->orderByDesc('id')->first();
        $this->assertSame($this->segundo->id, $siguiente->turno_id);
        $this->assertNotSame($this->primero->paciente_id, $siguiente->paciente_id);
        $this->assertSame(EvaluacionAdelanto::query()->sole()->id, SalidaOfertaAdelanto::query()->sole()->evaluacion_id);
    }

    #[DataProvider('limitesContinuacion')]
    public function test_continuacion_con_margen_exacto_de_cuatro_horas(string $instante, string $resultado): void
    {
        // Mantiene la identidad inicial; ajusta sólo el reloj de activación mediante fixture aislado.
        DB::table('ofertas_adelanto')->where('id', $this->oferta->id)->update([
            'activada_at' => '2026-11-16 04:00:00.000000', 'vence_at' => '2026-11-16 06:00:00.000000',
        ]);
        DB::table('despachos_oferta_adelanto')->where('id', $this->despacho->id)->update(['vence_at' => '2026-11-16 06:00:00.000000']);
        $this->ahora($instante);
        $this->ciclo();
        $this->assertSame($resultado, SalidaOfertaAdelanto::query()->sole()->continuacion);
        $this->assertDatabaseCount('ofertas_adelanto', $resultado === 'oferta_preparada' ? 2 : 1);
    }

    public static function limitesContinuacion(): array
    {
        return [['2026-11-16 06:00:00', 'oferta_preparada'], ['2026-11-16 06:00:00.000001', 'plazo_agotado']];
    }

    public function test_alta_que_ocupa_el_cupo_antes_de_continuar_impide_otra_oferta(): void
    {
        $this->cerrar();
        $paciente = $this->pacienteUc07();
        app(ConfirmarPrimeraConsultaAction::class)->ejecutar($paciente->user, (string) Str::uuid(), $this->profesional->id,
            CarbonImmutable::parse('2026-11-16 10:00:00', config('app.timezone')), 'Nueva solicitud');
        $this->ciclo();
        $this->assertDatabaseCount('ofertas_adelanto', 1);
        $this->assertNull(SalidaOfertaAdelanto::query()->sole()->oferta_siguiente_id);
    }

    public function test_agotamiento_libera_cupo_y_repetir_no_inicia_otra_oferta(): void
    {
        $this->segundo->forceFill(['estado' => 'CANCELADO'])->save();
        $this->cerrar();
        $this->ciclo();
        $this->ciclo();
        $this->assertSame('sin_candidatos', SalidaOfertaAdelanto::query()->sole()->continuacion);
        $this->assertDatabaseCount('ofertas_adelanto', 1);
        $this->assertCupoLibre();
    }

    public function test_rollback_del_cierre_conserva_reserva_y_no_crea_resultado(): void
    {
        Auditoria::creating(function (Auditoria $registro): void {
            if ($registro->evento === 'oferta_adelanto_salida_registrada') {
                throw new RuntimeException('Fallo sintético');
            }
        });
        try {
            $this->cerrar();
            $this->fail('Debió revertir el cierre.');
        } catch (RuntimeException) {
            $this->assertSame(OfertaAdelanto::ACTIVA, $this->oferta->fresh()->estado);
            $this->assertDatabaseCount('salidas_oferta_adelanto', 0);
        }
    }

    public function test_rollback_de_continuacion_es_recuperable_sin_duplicar_la_siguiente(): void
    {
        $this->cerrar();
        DespachoOfertaAdelanto::creating(fn () => throw new RuntimeException('Fallo sintético'));
        try {
            $this->ciclo();
            $this->fail('Debió revertir la segunda oferta.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('ofertas_adelanto', 1);
            $this->assertSame('pendiente', SalidaOfertaAdelanto::query()->sole()->continuacion);
        }
        Event::forget('eloquent.creating: '.DespachoOfertaAdelanto::class);
        $this->ciclo();
        $this->assertDatabaseCount('ofertas_adelanto', 2);
    }

    public function test_temporal_reutiliza_oferta_enlace_y_vencimiento_y_el_job_viejo_no_reenvia(): void
    {
        $fake = $this->transporte(['temporal', 'aceptado']);
        $original = $this->oferta->getAttributes();
        $hash = $this->despacho->token_hash;
        $this->enviar();
        $this->assertSame('REINTENTO', $this->despacho->fresh()->estado);
        $this->assertSame('12:05:00', $this->despacho->fresh()->proximo_intento_at->format('H:i:s'));
        $this->ahora('2026-11-15 12:04:59');
        $this->enviar(2);
        $this->assertSame(1, $fake->llamadas);
        $this->ahora('2026-11-15 12:05:00');
        $this->enviar(1);
        $this->assertSame(1, $fake->llamadas);
        $this->artisan('turnos:procesar-cupos')->assertSuccessful();
        Queue::assertPushed(DespacharOfertaAdelantoJob::class, fn ($job): bool => $job->despachoId === $this->despacho->id && $job->numero === 2);
        $this->enviar(2);
        $this->assertSame(2, $fake->llamadas);
        $this->assertSame($hash, $this->despacho->fresh()->token_hash);
        $this->assertSame($original, $this->oferta->fresh()->getAttributes());
        Mail::assertSent(OfertaAdelantoMail::class, fn ($mail): bool => str_contains($mail->render(), $this->url));
        $this->assertDatabaseCount('despachos_oferta_adelanto', 1);
        $this->assertDatabaseCount('intentos_despacho_adelanto', 2);
    }

    public function test_rf125_admite_solo_tres_reintentos_y_cierra_al_agotarlos(): void
    {
        $fake = $this->transporte(['temporal', 'temporal', 'temporal', 'temporal']);
        $this->enviar();
        foreach ([2 => '12:05:00', 3 => '12:15:00', 4 => '12:35:00'] as $numero => $hora) {
            $this->ahora('2026-11-15 '.$hora);
            $this->enviar($numero);
        }
        $this->enviar(5);
        $this->assertSame(4, $fake->llamadas);
        $this->assertSame('FALLIDO', $this->despacho->fresh()->estado);
        $this->assertSame(OfertaAdelanto::FALLO_ENVIO, $this->oferta->fresh()->estado);
        $this->assertDatabaseCount('intentos_despacho_adelanto', 4);
        $this->assertCupoLibre();
    }

    #[DataProvider('limitesReintento')]
    public function test_ventana_de_una_hora_desde_primer_fallo_es_inclusiva(string $instante, int $llamadas): void
    {
        $fake = $this->transporte(['temporal', 'aceptado']);
        $this->enviar();
        $this->ahora($instante);
        $this->enviar(2);
        $this->assertSame($llamadas, $fake->llamadas);
    }

    public static function limitesReintento(): array
    {
        return [['2026-11-15 13:00:00', 2], ['2026-11-15 13:00:00.000001', 1]];
    }

    public function test_definitivo_cierra_libera_audita_y_permite_continuar(): void
    {
        $this->transporte(['definitivo']);
        $antes = $this->primero->getAttributes();
        $this->enviar();
        $this->assertSame(OfertaAdelanto::FALLO_ENVIO, $this->oferta->fresh()->estado);
        $this->assertSame('FALLO_DEFINITIVO', IntentoDespachoAdelanto::query()->sole()->resultado);
        $this->assertSame($antes, $this->primero->fresh()->getAttributes());
        $this->assertCupoLibre();
        $this->assertSame(1, Auditoria::query()->where('evento', 'oferta_adelanto_salida_registrada')->count());
        $this->ciclo();
        $this->assertDatabaseCount('ofertas_adelanto', 2);
    }

    public function test_incierto_conserva_evidencia_no_reenvia_y_se_cierra_al_vencer(): void
    {
        $fake = $this->transporte(['incierto']);
        try {
            $this->enviar();
            $this->fail('Debió informar incertidumbre.');
        } catch (RuntimeException) {
            $this->assertSame('INCIERTO', $this->despacho->fresh()->estado);
        }
        $this->ahora('2026-11-15 12:10:00');
        $this->enviar(2);
        $this->artisan('turnos:procesar-cupos')->assertSuccessful();
        Queue::assertNotPushed(DespacharOfertaAdelantoJob::class);
        $this->assertNotNull($this->despacho->fresh()->material_cifrado);
        CarbonImmutable::setTestNow($this->oferta->vence_at);
        $this->ciclo();
        $this->assertSame(1, $fake->llamadas);
        $this->assertSame('INCIERTO', IntentoDespachoAdelanto::query()->sole()->resultado);
        $this->assertSame(OfertaAdelanto::VENCIDA, $this->oferta->fresh()->estado);
    }

    public function test_sondeo_recupera_un_fallo_terminal_interrumpido_antes_del_cierre(): void
    {
        $this->transporte(['definitivo']);
        Auditoria::creating(function (Auditoria $registro): void {
            if ($registro->evento === 'oferta_adelanto_salida_registrada') {
                throw new RuntimeException('Interrupción posterior al fallo técnico');
            }
        });
        try {
            $this->enviar();
            $this->fail('Debió interrumpirse el cierre.');
        } catch (RuntimeException) {
            $this->assertSame('FALLIDO', $this->despacho->fresh()->estado);
            $this->assertSame(OfertaAdelanto::ACTIVA, $this->oferta->fresh()->estado);
            $this->assertDatabaseCount('salidas_oferta_adelanto', 0);
        }
        Event::forget('eloquent.creating: '.Auditoria::class);
        $this->ciclo();
        $this->assertSame(OfertaAdelanto::FALLO_ENVIO, $this->oferta->fresh()->estado);
        $this->assertDatabaseCount('ofertas_adelanto', 2);
    }

    #[DataProvider('esperasAntesDeTransporte')]
    public function test_revalida_ventanas_despues_de_reclamar_antes_de_enviar(string $caso, string $estado, int $llamadas): void
    {
        $fake = $this->transporte(['temporal', 'aceptado']);
        if ($caso === 'hora') {
            $this->enviar();
            $this->ahora('2026-11-15 12:59:59');
        } else {
            $this->ahora('2026-11-15 13:59:59');
        }
        DB::listen(function ($evento) use ($caso): void {
            if (str_starts_with($evento->sql, 'insert into "intentos_despacho_adelanto"')) {
                $this->ahora($caso === 'hora' ? '2026-11-15 13:00:00.000001' : '2026-11-15 14:00:00');
            }
        });
        $this->enviar($caso === 'hora' ? 2 : 1);
        $this->assertSame($llamadas, $fake->llamadas);
        $this->assertSame($estado, $this->oferta->fresh()->estado);
    }

    public static function esperasAntesDeTransporte(): array
    {
        return [['hora', OfertaAdelanto::FALLO_ENVIO, 1], ['vigencia', OfertaAdelanto::VENCIDA, 0]];
    }

    public function test_cookie_de_otro_proposito_y_asociacion_de_otro_paciente_se_rechazan(): void
    {
        $this->autorizar();
        $datos = $this->autorizacion;
        $datos['uso'] = 'uc28-navegador-v1';
        $this->cookie = Crypt::encryptString(json_encode($datos));
        $this->rechazarHttp()->assertForbidden();
        DB::table('despachos_oferta_adelanto')->where('id', $this->despacho->id)->update(['paciente_id' => $this->segundo->paciente_id]);
        $this->get($this->url)->assertForbidden()->assertDontSee('Horario original');
        $this->assertDatabaseCount('salidas_oferta_adelanto', 0);
    }

    public function test_esperar_para_registrar_el_fallo_no_extiende_la_hora_de_reintentos(): void
    {
        $fake = $this->transporte(['temporal']);
        DB::listen(function ($evento): void {
            if (str_starts_with($evento->sql, 'select ') && str_contains($evento->sql, 'from "intentos_despacho_adelanto"')) {
                $this->ahora('2026-11-15 12:50:00');
            }
        });
        $this->enviar();
        $this->assertSame('12:00:00', $this->despacho->fresh()->primer_fallo_at->format('H:i:s'));
        $this->assertSame('12:05:00', $this->despacho->fresh()->proximo_intento_at->format('H:i:s'));
        $this->ahora('2026-11-15 13:00:00.000001');
        $this->enviar(2);
        $this->assertSame(1, $fake->llamadas);
        $this->assertSame(OfertaAdelanto::FALLO_ENVIO, $this->oferta->fresh()->estado);
    }

    public function test_sondeo_pagina_ofertas_sin_repetir_el_primer_lote(): void
    {
        $atributos = $this->oferta->getRawOriginal();
        unset($atributos['id']);
        for ($indice = 0; $indice < 100; $indice++) {
            $turno = $this->candidato('2026-11-23 10:00:00');
            DB::table('ofertas_adelanto')->insert(array_replace($atributos, [
                'turno_id' => $turno->id, 'paciente_id' => $turno->paciente_id,
            ]));
        }
        $paginas = 0;
        DB::listen(function ($evento) use (&$paginas): void {
            if (str_starts_with($evento->sql, 'select "id" from "ofertas_adelanto"')) {
                if (++$paginas > 2) {
                    throw new RuntimeException('El sondeo repitió un lote.');
                }
            }
        });
        $this->artisan('turnos:procesar-cupos')->assertExitCode(0);
        Queue::assertPushed(RevisarCicloOfertaAdelantoJob::class, 101);
        $ids = Queue::pushed(RevisarCicloOfertaAdelantoJob::class)->map(fn ($job): int => $job->ofertaId);
        $this->assertCount(101, $ids->unique());
        $this->assertSame(2, $paginas);
    }

    private function preparar(string $instante): void
    {
        $this->ahora($instante);
        app(ProcesarIntencionCupoService::class)->procesar($this->intencion->id);
        $this->oferta = OfertaAdelanto::query()->sole();
        $this->despacho = DespachoOfertaAdelanto::query()->sole();
        $this->url = json_decode(Crypt::decryptString($this->despacho->material_cifrado), true)['url'];
        parse_str(parse_url($this->url, PHP_URL_QUERY), $query);
        $this->token = $query['token'];
    }

    private function autorizar(): void
    {
        $respuesta = $this->get($this->url)->assertOk();
        $this->cookie = $respuesta->headers->getCookies()[0]->getValue();
        $this->autorizacion = json_decode(Crypt::decryptString($this->cookie), true);
    }

    private function rechazarHttp()
    {
        return $this->withUnencryptedCookies([ProtegerAccesoOfertaAdelanto::COOKIE => $this->cookie])
            ->post('https://consultorio.example.test/turnos/adelanto/rechazar', [
                '_csrf_oferta' => $this->autorizacion['csrf'], 'accion' => 'rechazar',
            ], ['Origin' => 'https://consultorio.example.test']);
    }

    private function cerrar(): void
    {
        app(CerrarOfertaAdelantoService::class)->cerrar($this->oferta->id, OfertaAdelanto::RECHAZADA, [
            'despacho' => $this->despacho->id, 'oferta' => $this->oferta->id, 'paciente' => $this->oferta->paciente_id,
        ]);
    }

    private function ciclo(): void
    {
        (new RevisarCicloOfertaAdelantoJob($this->oferta->id))->handle(app(CerrarOfertaAdelantoService::class), app(ContinuarCupoAdelantoService::class));
    }

    private function enviar(int $numero = 1): void
    {
        app(DespacharOfertaAdelantoService::class)->despachar($this->despacho->id, $numero);
    }

    private function transporte(array $resultados): SimularEnvioOfertaAdelanto
    {
        $fake = new SimularEnvioOfertaAdelanto(Mail::getFacadeRoot()->manager);
        $fake->resultados = $resultados;
        Mail::swap($fake);

        return $fake;
    }

    private function candidato(string $inicio): Turno
    {
        $fecha = CarbonImmutable::parse($inicio, config('app.timezone'));

        return Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CONFIRMADO', 'inicio_at' => $fecha,
            'fin_at' => $fecha->addMinutes(30), 'motivo_solicitud' => 'MOTIVO PRIVADO',
        ])->fresh();
    }

    private function ahora(string $instante): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($instante, config('app.timezone')));
    }

    private function estadoPersistido(): array
    {
        return array_map(fn (string $tabla): string => DB::table($tabla)->orderBy('id')->get()->toJson(), [
            'turnos', 'ofertas_adelanto', 'despachos_oferta_adelanto', 'salidas_oferta_adelanto', 'auditorias',
        ]);
    }

    private function assertCupoLibre(): void
    {
        $fecha = CarbonImmutable::parse('2026-11-16', config('app.timezone'));
        $horas = array_map(fn (array $slot): string => $slot['inicio_at']->format('H:i:s'),
            app(DisponibilidadPrimeraConsultaService::class)->consultarDisponibilidad($this->profesional, $fecha, $fecha));
        $this->assertContains('10:00:00', $horas);
    }
}
