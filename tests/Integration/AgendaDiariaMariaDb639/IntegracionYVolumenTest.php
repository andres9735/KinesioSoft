<?php

namespace Tests\Integration\AgendaDiariaMariaDb639;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Enums\DiaSemanaEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Jobs\DespacharRecordatorioTurnoJob;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntentoRecordatorioTurno;
use App\Models\Turno;
use App\Services\Agenda\GenerarAgendaDiariaService;
use App\Services\Agenda\PrepararJornadaAgendaService;
use App\Services\Turnos\DespacharRecordatorioTurnoService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\PrepararContenidoRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Caso639;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Entorno639;

/** EI con datos sintéticos. No certifica clínica, reprogramación profesional ni consumidor de cupos ausentes. */
class IntegracionYVolumenTest extends Caso639
{
    private function enlace(Turno $t): string
    {
        return app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($t->id)->urlParaCorreo();
    }

    private function autorizar(string $url): array
    {
        $r = $this->get($url)->assertOk()->assertDontSee('CLINICO-SINTETICO-639-NO-EXPONER');
        preg_match('/name="_csrf_enlace" value="([a-f0-9]+)"/', $r->getContent(), $m);
        $this->assertTrue(isset($m[1]));
        $cookies = [];
        foreach ($r->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        return ['csrf' => $m[1], 'cookies' => $cookies];
    }

    private function decidir(array $a, string $accion, int $http = 303): void
    {
        $this->withHeaders(['Origin' => config('app.url'), 'Sec-Fetch-Site' => 'same-origin'])
            ->post(config('app.url').'/turnos/asistencia/decidir', ['_csrf_enlace' => $a['csrf'], 'accion' => $accion, 'acepto' => '1'])
            ->assertStatus($http);
    }

    private function agenda(Turno $t, string $estado): void
    {
        $this->actingAs($t->kinesiologa->user)->get(route('turnos.agenda-atencion.index', ['fecha' => $t->inicio_at->toDateString()]))
            ->assertOk()->assertViewHas('turnos', fn ($datos) => collect($datos)->contains(fn ($d) => $d['id'] === $t->id && $d['estado']->value === $estado))
            ->assertDontSee('CLINICO-SINTETICO-639-NO-EXPONER');
        Auth::forgetGuards();
        $this->interno();
    }

    public function test_ei01_uc07_alta_ocho_horas_recordatorio_y_agenda(): void
    {
        $p = $this->profesionalUc07();
        $inicio = CarbonImmutable::now()->addHours(8);
        // La franja de lunes preexistente del helper es 10–12; esta franja no la solapa.
        $p->configuracionesAgenda()->first()->franjasHorarias()->create([
            'dia_semana' => DiaSemanaEnum::from($inicio->isoWeekday()), 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00',
        ]);
        $t = app(ConfirmarPrimeraConsultaAction::class)->ejecutar($this->pacienteUc07()->user, (string) Str::uuid(), $p->id, $inicio, 'Fixture sintético 639')->turno;
        $ids = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar();
        $id = DB::table('recordatorios_turno')->where('turno_id', $t->id)->value('id');
        $this->assertContains($id, $ids);
        $url = $this->enlace($t);
        $a = $this->autorizar($url);
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertTrue(str_contains($html, 'value="confirmar"'));
        $this->assertFalse(str_contains($html, 'value="cancelar"'));
        $this->interno();
        app()->call([new DespacharRecordatorioTurnoJob($id, (string) Str::uuid()), 'handle']);
        $this->decidir($a, 'confirmar');
        $this->agenda($t->fresh(), 'CONFIRMADO');
    }

    #[DataProvider('limites')]
    public function test_ei02_cancelacion_limite_inclusivo_microsegundo(bool $exacto): void
    {
        $t = $this->turno(12, 'CONFIRMADO');
        $a = $this->autorizar($this->enlace($t));
        if (! $exacto) {
            CarbonImmutable::setTestNow(CarbonImmutable::now()->addMicrosecond());
        }
        $this->decidir($a, 'cancelar');
        $this->agenda($t->fresh(), $exacto ? 'CANCELADO' : 'CONFIRMADO');
        $this->assertSame($exacto ? 1 : 0, DB::table('intenciones_evaluacion_cupo')->where('turno_id', $t->id)->count());
    }

    public static function limites(): array
    {
        return [[true], [false]];
    }

    public function test_ei03_confirmacion_futura_y_repeticion_al_inicio(): void
    {
        $t = $this->turno(1);
        $url = $this->enlace($t);
        CarbonImmutable::setTestNow($t->inicio_at->subSecond());
        $a = $this->autorizar($url);
        $this->decidir($a, 'confirmar');
        CarbonImmutable::setTestNow($t->inicio_at);
        $this->decidir($a, 'confirmar', 410);
        $this->assertSame(1, Auditoria::where('auditable_id', $t->id)->where('evento', 'turno_asistencia_confirmada')->count());
    }

    public function test_ei04_mismo_enlace_confirma_y_cancela_una_vez(): void
    {
        $t = $this->turno();
        $url = $this->enlace($t);
        $a = $this->autorizar($url);
        $this->decidir($a, 'confirmar');
        $this->decidir($a, 'cancelar');
        $this->decidir($a, 'cancelar');
        $this->get($url)->assertStatus(409);
        $this->assertSame(1, DB::table('intenciones_evaluacion_cupo')->where('turno_id', $t->id)->count());
        $this->agenda($t->fresh(), 'CANCELADO');
    }

    public function test_ei05_aba_no_autoriza_despacho_ni_decision_obsoletos(): void
    {
        $t = $this->turno();
        $id = $this->recordatorio($t);
        $url = $this->enlace($t);
        $a = $this->autorizar($url);
        $this->interno();
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($id);
        DB::table('turnos')->where('id', $t->id)->update(['inicio_at' => $t->inicio_at->addHour()]);
        DB::table('turnos')->where('id', $t->id)->update(['inicio_at' => $t->getRawOriginal('inicio_at')]);
        try {
            app(DespacharRecordatorioTurnoService::class)->despachar($id, (string) Str::uuid());
        } catch (RuntimeException) {
            // El rechazo puede expresarse como no elegible o excepción sanitizada.
        }
        $this->get($url)->assertStatus(409);
        $this->decidir($a, 'confirmar');
        $this->assertSame('PENDIENTE', $t->fresh()->estado->value);
        $this->assertSame(0, IntentoRecordatorioTurno::where('recordatorio_id', $id)->count());
    }

    public function test_ei06_incertidumbre_y_repeticion_del_job_sin_otro_transporte(): void
    {
        $t = $this->turno();
        $id = $this->recordatorio($t);
        $transportes = 0;
        self::transporteSimulado($this->app, Resultado::INCIERTO, function () use (&$transportes): void {
            $transportes++;
        });
        $job = new DespacharRecordatorioTurnoJob($id, (string) Str::uuid());
        app()->call([$job, 'handle']);
        app()->call([unserialize(serialize($job)), 'handle']);
        $this->assertSame(1, $transportes);
        $this->assertSame(1, IntentoRecordatorioTurno::where('recordatorio_id', $id)->count());
        $this->assertNull(IntentoRecordatorioTurno::where('recordatorio_id', $id)->sole()->evidencia_entrega);
        $this->assertFalse(str_contains(serialize($job), '?token='));
    }

    public function test_ei07_get_head_sin_consumo_ni_transiciones(): void
    {
        $t = $this->turno();
        $url = $this->enlace($t);
        $antes = $t->getAttributes();
        $audits = Auditoria::count();
        for ($i = 0; $i < 2; $i++) {
            $this->head($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
            $this->get($url)->assertOk()->assertDontSee('CLINICO-SINTETICO-639-NO-EXPONER');
        }
        $this->assertSame($antes, $t->fresh()->getAttributes());
        $this->assertSame($audits, Auditoria::count());
        $this->assertNull(EnlaceAsistenciaTurno::where('turno_id', $t->id)->sole()->revocado_at);
    }

    #[DataProvider('ordenesCancelacion')]
    public function test_ei08_cancelacion_portal_y_http_en_procesos_reales(bool $portalPrimero): void
    {
        $t = $this->turno();
        $antes = $t->getAttributes();
        $ocupacion = fn (): int => Turno::where('kinesiologa_id', $t->kinesiologa_id)
            ->whereIn('estado', EstadoTurnoEnum::estadosQueReservanAgenda())
            ->where('inicio_at', '<', $t->fin_at)->where('fin_at', '>', $t->inicio_at)->count();
        $this->assertSame(1, $ocupacion());
        $a = $this->autorizar($this->enlace($t));
        $portal = ['turno' => $t->id];
        $this->iniciar('uno', $portalPrimero ? 'cancelar' : 'cancelar_enlace', ($portalPrimero ? $portal : $a) + ['pausar' => true]);
        $this->barrera->esperar('uno-protegido');
        $this->iniciar('dos', $portalPrimero ? 'cancelar_enlace' : 'cancelar', ($portalPrimero ? $a : $portal) + ['observar_mutex' => true]);
        $this->barrera->esperar('dos-intentando-mutex');
        // Mantener el primer lock mientras el segundo ejecuta su SELECT FOR UPDATE real.
        usleep(400000);
        $this->barrera->marcar('uno-continuar');
        $uno = $this->resultado('uno');
        $this->assertTrue($uno['ok']);
        $dos = $this->resultado('dos');
        $this->assertTrue($dos['ok']);
        $this->assertNotSame($uno['sesion'], $dos['sesion']);
        $this->assertGreaterThanOrEqual(300, $dos['mutex_espera_ms']);
        $this->assertSame(303, ($portalPrimero ? $dos : $uno)['valor']);
        $this->assertTrue(app(CancelarTurnoAction::class)->ejecutar($t->paciente->user, $t)->esReplay);
        $this->assertSame('CANCELADO', $t->fresh()->estado->value);
        $this->assertSame(0, $ocupacion());
        $actual = $t->fresh()->getAttributes();
        unset($antes['estado'], $antes['updated_at'], $actual['estado'], $actual['updated_at']);
        $this->assertSame($antes, $actual);
        $this->assertSame(1, Auditoria::where('auditable_id', $t->id)->where('evento', 'turno_cancelado')->count());
        $this->assertSame(1, DB::table('intenciones_evaluacion_cupo')->where('turno_id', $t->id)->count());
        $this->assertNull(DB::table('intenciones_evaluacion_cupo')->where('turno_id', $t->id)->value('procesado_at'));
        fwrite(STDOUT, 'EI08_639 '.json_encode(['orden' => $portalPrimero ? 'portal-enlace' : 'enlace-portal', 'sesiones_distintas' => true,
            'espera_mutex_ms' => $dos['mutex_espera_ms'], 'cancelaciones' => 1, 'auditorias' => 1, 'intenciones' => 1, 'ocupacion_final' => 0,
            'turno_conservado' => true, 'procesado' => false]).PHP_EOL);
    }

    public static function ordenesCancelacion(): array
    {
        return ['portal primero' => [true], 'enlace primero' => [false]];
    }

    public function test_ei09_generacion_fallida_no_impide_consulta_actual(): void
    {
        $this->profesionalUc07();
        $fallar = true;
        DB::listen(function ($e) use (&$fallar): void {
            if ($fallar && preg_match('/\bfrom `kinesiologas`/i', $e->sql)) {
                $fallar = false;
                throw new RuntimeException('Fallo sintético 639');
            }
        });
        $e = app(GenerarAgendaDiariaService::class)->ejecutar();
        $this->assertFalse($fallar);
        $this->assertSame('FALLIDA', $e->resultado->value);
        $this->agenda($this->turno(8), 'PENDIENTE');
    }

    public function test_ei10_exportacion_anterior_y_consulta_posterior_independientes(): void
    {
        $t = $this->turno();
        $fecha = ['fecha' => $t->inicio_at->toDateString()];
        $this->actingAs($t->kinesiologa->user);
        $impresion = $this->get(route('turnos.agenda-atencion.imprimir', $fecha))->assertOk();
        $pdf = $this->get(route('turnos.agenda-atencion.pdf', $fecha))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $bytes = $pdf->getContent();
        $this->assertTrue(str_starts_with($bytes, '%PDF-'));
        $html = $impresion->getContent();
        $this->assertFalse(str_contains($html, 'CLINICO-SINTETICO-639-NO-EXPONER'));
        $this->interno();
        app(CancelarTurnoAction::class)->ejecutar($t->paciente->user, $t);
        $this->agenda($t->fresh(), 'CANCELADO');
        $this->assertSame($html, $impresion->getContent());
        $this->assertSame(hash('sha256', $bytes), hash('sha256', $pdf->getContent()));
    }

    #[DataProvider('volumenes')]
    public function test_mediciones_acotadas_sinteticas_sin_inventar_cotas(int $cantidad): void
    {
        $p = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        $inicio = CarbonImmutable::now()->addDay()->startOfDay();
        $ids = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $hora = $inicio->addDays(intdiv($i, 30))->addMinutes(($i % 30) * 30);
            $ids[] = Turno::factory()->for($p)->for($paciente)->create([
                'inicio_at' => $hora, 'fin_at' => $hora->addMinutes(30), 'tipo' => 'SEGUIMIENTO', 'estado' => 'PENDIENTE',
            ])->id;
        }
        $this->actingAs($p->user);
        $parametros = ['fecha' => $inicio->toDateString()];
        // Calentamiento separado; las cotas corresponden a jornada habitual de 30.
        $this->get(route('turnos.agenda-atencion.index', $parametros))->assertOk();
        $mediciones = [];
        foreach (['agenda' => ['turnos.agenda-atencion.index', $parametros, 2],
            'filtros' => ['turnos.agenda-atencion.index', $parametros + ['estado' => 'PENDIENTE'], 1],
            'pdf' => ['turnos.agenda-atencion.pdf', $parametros, 10]] as $operacion => [$ruta, $args, $cota]) {
            [$respuesta, $medida] = $this->medir(fn () => $this->get(route($ruta, $args))->assertOk());
            $mediciones[$operacion] = $medida;
            if ($operacion === 'pdf') {
                $this->assertTrue(str_starts_with($respuesta->getContent(), '%PDF-'));
            }
            $this->assertLessThanOrEqual($cota, $medida['segundos']);
        }
        $this->interno();
        [$preparada, $mediciones['uc29_profesional']] = $this->medir(fn () => app(PrepararJornadaAgendaService::class)->preparar($p, $inicio));
        $this->assertCount(30, $preparada->turnos);
        $this->assertLessThanOrEqual(60, $mediciones['uc29_profesional']['segundos']);
        [, $mediciones['uc29_generacion_total']] = $this->medir(fn () => app(GenerarAgendaDiariaService::class)->ejecutar($inicio));
        [$elegidos, $mediciones['seleccion']] = $this->medir(fn () => app(SeleccionarRecordatoriosTurnoService::class)->seleccionar());
        $propios = DB::table('recordatorios_turno')->whereIn('turno_id', $ids)->whereIn('id', $elegidos)->pluck('id');
        $mediciones['seleccion']['elegibles_propios'] = $propios->count();
        [, $mediciones['preparacion']] = $this->medir(function () use ($propios): void {
            foreach ($propios as $id) {
                app(PrepararContenidoRecordatorioTurnoService::class)->preparar($id);
            }
        });
        [, $mediciones['despacho_simulado']] = $this->medir(function () use ($propios): void {
            foreach ($propios as $id) {
                app(DespacharRecordatorioTurnoService::class)->despachar($id, (string) Str::uuid());
            }
        });
        // Sólo números/condiciones: no SQL, bindings, contenido, pacientes, URLs ni credenciales.
        fwrite(STDOUT, 'METRICAS_639 '.json_encode(['turnos_sinteticos' => $cantidad, 'jornada' => 30,
            'carga' => 'caliente', 'mediciones' => $mediciones, 'heap_actual_bytes' => memory_get_usage(true),
            'reserva' => 'incluye guardas PDO; consultas_laravel no cuenta SELECT de identidad']).PHP_EOL);
    }

    public static function volumenes(): array
    {
        return [[30], [150], [600]];
    }

    private function medir(callable $operacion): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $identidades = Entorno639::lecturasIdentidad();
        $inicio = hrtime(true);
        try {
            $resultado = $operacion();
            $segundos = (hrtime(true) - $inicio) / 1e9;
            $consultas = DB::getQueryLog();
            $frecuencias = array_count_values(array_column($consultas, 'query'));

            return [$resultado, ['segundos' => $segundos, 'consultas_laravel' => count($consultas),
                'consultas_guardas_pdo' => Entorno639::lecturasIdentidad() - $identidades,
                'formas_sql_repetidas' => count(array_filter($frecuencias, fn ($n) => $n > 1)),
                'max_repeticiones_sql' => $frecuencias === [] ? 0 : max($frecuencias)]];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
