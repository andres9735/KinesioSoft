<?php

namespace Tests\Integration\AdelantosMariaDb09;

use App\Actions\Turnos\AceptarOfertaAdelantoAction;
use App\Actions\Turnos\CancelarTurnoAction;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\Auditoria;
use App\Models\DespachoConfirmacionAdelanto;
use App\Models\DespachoOfertaAdelanto;
use App\Models\EvaluacionAdelanto;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Turnos\ConsultarOfertaAdelantoService;
use App\Services\Turnos\ProcesarIntencionCupoService;
use App\Services\Turnos\SeleccionarCandidatoAdelantoService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\AdelantosMariaDb09\Support\Caso639;

/** Fixtures nuevos por escenario; sin limpieza ni transacción envolvente. */
class CicloConcurrenteTest extends Caso639
{
    private function escenario(bool $ofertar = true, string $inicio = '10:15:00'): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-15 10:00:00', config('app.timezone')));
        $k = $this->profesionalUc07();
        $k->configuracionesAgenda()->sole()->update(['vigencia_desde' => '2026-11-16']);
        $c = Turno::factory()->for($k)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'PENDIENTE',
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00',
        ])->fresh();
        $p = $this->pacienteUc07();
        $t = Turno::factory()->for($k)->for($p)->create([
            'tipo' => 'SEGUIMIENTO', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-16 '.$inicio, 'fin_at' => '2026-11-16 11:00:00',
        ]);
        $cancelacionInicio = hrtime(true);
        app(CancelarTurnoAction::class)->ejecutar($p->user, $t);
        $cancelacionMs = (hrtime(true) - $cancelacionInicio) / 1e6;
        $i = IntencionEvaluacionCupo::where('turno_id', $t->id)->sole();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-15 12:00:00', config('app.timezone')));
        $o = null;
        $auth = [];
        if ($ofertar) {
            app(ProcesarIntencionCupoService::class)->procesar($i->id);
            $o = OfertaAdelanto::where('intencion_evaluacion_cupo_id', $i->id)->sole();
            $d = DespachoOfertaAdelanto::where('oferta_id', $o->id)->sole();
            $auth = ['uso' => 'adelanto-navegador-v1', 'oferta' => $o->id, 'paciente' => $o->paciente_id, 'despacho' => $d->id, 'csrf' => str_repeat('a', 64), 'exp' => now()->addHours(3)->timestamp];
        }

        return ['k' => $k->id, 'turno' => $c->id, 'intencion' => $i->id, 'oferta' => $o?->id, 'auth' => $auth, 'cancelacion_ms' => $cancelacionMs];
    }

    private function carrera(string $a, string $b, array $da, ?array $db = null): array
    {
        $this->iniciar('primero', $a, $da + ['pausar' => true]);
        $this->barrera->esperar('primero-protegido');
        $this->iniciar('segundo', $b, $db ?? $da);
        $this->barrera->esperar('segundo-intentando');
        usleep(250000); // Ganador conserva el mutex; el otro proceso ya intentó el SELECT.
        $this->barrera->marcar('primero-continuar');
        $r = [$this->resultado('primero'), $this->resultado('segundo')];
        $this->assertNotSame($r[0]['pid'], $r[1]['pid']);
        $this->assertNotSame($r[0]['sesion'], $r[1]['sesion']);
        $this->assertGreaterThan(200, $r[1]['espera_ms']);
        file_put_contents(storage_path('carrera-'.bin2hex(random_bytes(4)).'.json'), json_encode(['operaciones' => [$a, $b], 'resultados' => $r], JSON_PRETTY_PRINT));

        return $r;
    }

    public function test_dos_consumidores_recuperan_una_evaluacion_oferta_y_despacho(): void
    {
        $d = $this->escenario(false);
        [$a, $b] = $this->carrera('procesar', 'procesar', $d);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame($a['valor'], $b['valor']);
        $this->assertSame(1, EvaluacionAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->count());
        $this->assertSame(1, OfertaAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->count());
        $this->assertSame(1, DespachoOfertaAdelanto::where('oferta_id', OfertaAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->value('id'))->count());
    }

    #[DataProvider('intencionesSuperpuestas')]
    public function test_activaciones_incompatibles_se_serializan(bool $otraCancelacion): void
    {
        $d = $this->escenario(false);
        $otro = Turno::factory()->for(Kinesiologa::find($d['k']))->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CONFIRMADO', 'inicio_at' => '2026-11-22 10:00:00', 'fin_at' => '2026-11-22 10:30:00',
        ]);
        $segundo = array_replace($d, ['turno' => $otro->id]);
        if ($otraCancelacion) {
            $paciente = $this->pacienteUc07();
            $cancelado = Turno::factory()->for(Kinesiologa::find($d['k']))->for($paciente)->create([
                'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CONFIRMADO', 'inicio_at' => '2026-11-16 10:30:00', 'fin_at' => '2026-11-16 11:00:00',
            ]);
            app(CancelarTurnoAction::class)->ejecutar($paciente->user, $cancelado);
            $segundo['intencion'] = IntencionEvaluacionCupo::where('turno_id', $cancelado->id)->sole()->id;
        }
        [$a, $b] = $this->carrera('activar', 'activar', $d, $segundo);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertNotNull($a['valor']);
        $this->assertNull($b['valor']);
        $this->assertSame(1, OfertaAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->count());
        if ($otraCancelacion) {
            $this->assertSame(0, OfertaAdelanto::where('intencion_evaluacion_cupo_id', $segundo['intencion'])->count());
        }
    }

    public static function intencionesSuperpuestas(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('respuestas')]
    public function test_primera_respuesta_y_vencimiento(string $primera, string $segunda, string $resultado): void
    {
        $d = $this->escenario();
        $de = $d;
        if ($primera === 'vencer') {
            $d['ahora'] = CarbonImmutable::now()->addHours(2)->toIso8601String();
        }
        if ($segunda === 'vencer') {
            $de['ahora'] = CarbonImmutable::now()->addHours(2)->toIso8601String();
        }
        [$a, $b] = $this->carrera($primera, $segunda, $d, $de);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame($resultado, OfertaAdelanto::find($d['oferta'])->estado);
        $this->assertSame(1, SalidaOfertaAdelanto::where('oferta_id', $d['oferta'])->count());
        $this->assertSame($resultado === OfertaAdelanto::ACEPTADA ? '2026-11-16' : '2026-11-23', Turno::find($d['turno'])->inicio_at->toDateString());
        $this->assertSame(1, IntencionEvaluacionCupo::where('kinesiologa_id', $d['k'])->count());
    }

    public static function respuestas(): array
    {
        return [['aceptar', 'rechazar', OfertaAdelanto::ACEPTADA], ['rechazar', 'aceptar', OfertaAdelanto::RECHAZADA], ['aceptar', 'aceptar', OfertaAdelanto::ACEPTADA], ['vencer', 'aceptar', OfertaAdelanto::VENCIDA], ['aceptar', 'vencer', OfertaAdelanto::ACEPTADA]];
    }

    #[DataProvider('ordenes')]
    public function test_ca_r11_bloqueo_y_aceptacion_en_ambos_ordenes(bool $aceptarPrimero): void
    {
        $d = $this->escenario();
        [$a, $b] = $this->carrera($aceptarPrimero ? 'aceptar' : 'bloquear', $aceptarPrimero ? 'bloquear' : 'aceptar', $d);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame($aceptarPrimero ? 1 : 0, $aceptarPrimero ? $b['valor'] : $a['valor']);
        $this->assertSame($aceptarPrimero ? OfertaAdelanto::ACEPTADA : OfertaAdelanto::CERRADA_AGENDA, OfertaAdelanto::find($d['oferta'])->estado);
        $this->assertSame($aceptarPrimero ? '2026-11-16' : '2026-11-23', Turno::find($d['turno'])->inicio_at->toDateString());
        $this->assertFalse(OfertaAdelanto::find($d['oferta'])->reservaVigenteEn(now()));
        if ($aceptarPrimero) {
            $this->assertNotNull($this->barrera->leer('segundo-snapshot-desactualizado'));
        }
    }

    public static function ordenes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('ordenes')]
    public function test_aceptacion_y_configuracion_invalidante(bool $aceptarPrimero): void
    {
        $d = $this->escenario();
        [$a, $b] = $this->carrera($aceptarPrimero ? 'aceptar' : 'anular_configuracion', $aceptarPrimero ? 'anular_configuracion' : 'aceptar', $d);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame($aceptarPrimero ? OfertaAdelanto::ACEPTADA : OfertaAdelanto::CERRADA_AGENDA, OfertaAdelanto::find($d['oferta'])->estado);
        $this->assertSame($aceptarPrimero ? '2026-11-16' : '2026-11-23', Turno::find($d['turno'])->inicio_at->toDateString());
    }

    public function test_dos_preparadores_de_confirmacion_recuperan_la_misma_identidad(): void
    {
        $d = $this->escenario();
        $this->iniciar('aceptado', 'aceptar', $d);
        $this->assertTrue($this->resultado('aceptado')['ok']);
        $s = SalidaOfertaAdelanto::where('oferta_id', $d['oferta'])->sole();
        [$a, $b] = $this->carrera('preparar_confirmacion', 'preparar_confirmacion', $d + ['salida' => $s->id]);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame($a['valor'], $b['valor']);
        $this->assertSame(1, DespachoConfirmacionAdelanto::where('salida_id', $s->id)->count());
    }

    public function test_dos_despachadores_confirmacion_tienen_un_solo_transporte_simulado(): void
    {
        $d = $this->escenario();
        $this->iniciar('aceptado', 'aceptar', $d);
        $this->assertTrue($this->resultado('aceptado')['ok']);
        $s = SalidaOfertaAdelanto::where('oferta_id', $d['oferta'])->sole();
        $dc = DespachoConfirmacionAdelanto::where('salida_id', $s->id)->sole();
        [$a, $b] = $this->carrera('despachar_confirmacion', 'despachar_confirmacion', $d + ['despacho' => $dc->id]);
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame(1, $a['valor']['transportes'] + $b['valor']['transportes']);
        $this->assertSame('ENVIADO', $dc->fresh()->estado);
    }

    #[DataProvider('ordenes')]
    public function test_reserva_frente_a_alta_con_horario_previamente_libre(bool $reservaPrimero): void
    {
        $d = $this->escenario(false, '10:00:00');
        $p = $this->pacienteUc07();
        $alta = $d + ['actor' => $p->user_id, 'uuid' => (string) Str::uuid(), 'inicio' => '2026-11-16T10:00:00-03:00'];
        [$a, $b] = $this->carrera($reservaPrimero ? 'activar' : 'alta', $reservaPrimero ? 'alta' : 'activar', $reservaPrimero ? $d : $alta, $reservaPrimero ? $alta : $d);
        $this->assertTrue($a['ok']);
        if ($reservaPrimero) {
            $this->assertFalse($b['ok']);
            $this->assertSame(SlotNoDisponibleException::class, $b['clase']);
            $this->assertSame(0, Turno::where('paciente_id', $p->id)->count());
        } else {
            $this->assertTrue($b['ok']);
            $this->assertNull($b['valor']);
            $this->assertSame(1, Turno::where('paciente_id', $p->id)->count());
        }
    }

    public function test_rollback_fisico_despues_de_escribir_y_reintento_sin_duplicados(): void
    {
        $d = $this->escenario();
        $audit = Auditoria::count();
        $revision = DB::table('revisiones_enlace_asistencia_turno')->where('turno_id', $d['turno'])->value('version_programacion');
        $this->iniciar('fallido', 'aceptar', $d + ['fallar' => true]);
        $r = $this->resultado('fallido');
        $this->assertFalse($r['ok']);
        $this->assertSame('2026-11-23', Turno::find($d['turno'])->inicio_at->toDateString());
        $this->assertSame(OfertaAdelanto::ACTIVA, OfertaAdelanto::find($d['oferta'])->estado);
        $this->assertSame(0, SalidaOfertaAdelanto::where('oferta_id', $d['oferta'])->count());
        $this->assertSame($audit, Auditoria::count());
        $this->assertSame($revision, DB::table('revisiones_enlace_asistencia_turno')->where('turno_id', $d['turno'])->value('version_programacion'));
        $this->iniciar('recuperado', 'aceptar', $d);
        $this->assertTrue($this->resultado('recuperado')['ok']);
        $s = SalidaOfertaAdelanto::where('oferta_id', $d['oferta'])->sole();
        $this->assertSame(1, DespachoConfirmacionAdelanto::where('salida_id', $s->id)->count());
        $this->assertSame((int) $revision + 1, (int) DB::table('revisiones_enlace_asistencia_turno')->where('turno_id', $d['turno'])->value('version_programacion'));
    }

    public function test_ddl_efectivo_unicidad_fk_y_precision(): void
    {
        $d = $this->escenario();
        $columnas = DB::select("SELECT COLUMN_NAME nombre, DATETIME_PRECISION precision_temporal FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ofertas_adelanto' AND COLUMN_NAME IN ('activada_at','vence_at','inicio_at')");
        $precision = array_column($columnas, 'precision_temporal', 'nombre');
        $this->assertSame(6, (int) $precision['activada_at']);
        $this->assertSame(6, (int) $precision['vence_at']);
        $this->assertSame(0, (int) $precision['inicio_at']);
        $fila = OfertaAdelanto::find($d['oferta'])->getAttributes();
        unset($fila['id']);
        try {
            DB::table('ofertas_adelanto')->insert($fila);
            $this->fail('Debe rechazar la identidad cancelación/paciente duplicada');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
        }
        $fila['paciente_id'] = $this->pacienteUc07()->id;
        $fila['turno_id'] = PHP_INT_MAX;
        try {
            DB::table('ofertas_adelanto')->insert($fila);
            $this->fail('Debe rechazar el turno inexistente');
        } catch (QueryException $e) {
            $this->assertSame(1452, $e->errorInfo[1]);
        }
        $this->assertSame(1, OfertaAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->count());
        $this->assertCount(4, DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()'));
    }

    public function test_cotas_con_volumen_y_carga_serial_definidos(): void
    {
        $resultados = [];
        foreach ([30, 150, 600] as $volumen) {
            $d = $this->escenario();
            $k = Kinesiologa::find($d['k']);
            $c = Turno::find($d['turno']);
            $modelo = $c->getAttributes();
            unset($modelo['id']);
            // Datos históricos sintéticos, fuera de la ventana y sin interferir en destino.
            $modelo['inicio_at'] = '2026-10-20 10:00:00';
            $modelo['fin_at'] = '2026-10-20 10:30:00';
            $modelo['estado'] = 'FINALIZADO';
            $modelo['clave_idempotencia'] = null;
            foreach (array_chunk(array_fill(0, $volumen - 1, $modelo), 100) as $lote) {
                DB::table('turnos')->insert($lote);
            }
            $o = OfertaAdelanto::find($d['oferta']);
            $consulta = app(ConsultarOfertaAdelantoService::class);
            $selector = app(SeleccionarCandidatoAdelantoService::class);
            foreach (['busqueda', 'consulta'] as $operacion) {
                $muestras = [];
                for ($n = 0; $n < 10; $n++) {
                    $t = hrtime(true);
                    $r = $operacion === 'busqueda' ? $selector->seleccionar($k, $o->inicio_at, $o->fin_at) : $consulta->porReferencia($d['auth']);
                    $ms = (hrtime(true) - $t) / 1e6;
                    $this->assertNotNull($r);
                    $this->assertLessThan(2000, $ms);
                    $muestras[] = $ms;
                }
                $resultados[] = ['volumen_profesional' => $volumen + 1, 'candidatos_en_ventana' => 1, 'operacion' => $operacion, 'ms' => $muestras];
            }
            $t = hrtime(true);
            $s = app(AceptarOfertaAdelantoAction::class)->ejecutar($d['oferta'], $d['auth']);
            $ms = (hrtime(true) - $t) / 1e6;
            $this->assertSame(OfertaAdelanto::ACEPTADA, $s->resultado);
            $this->assertLessThan(2000, $ms);
            $resultados[] = ['volumen_profesional' => $volumen + 1, 'operacion' => 'reprogramacion', 'ms' => [$ms]];
        }
        file_put_contents(base_path('storage/framework/testing/asignacion-cierre-20261117-09/rendimiento-mariadb.json'), json_encode(['entorno' => 'PHP 8.2.12 / MariaDB 10.11.19 loopback / REPEATABLE READ / guardas por consulta', 'carga' => '1 operación a la vez, sin tráfico HTTP ni SMTP; fixtures y bootstrap excluidos; primera muestra incluida', 'muestras' => $resultados], JSON_PRETTY_PRINT));
    }

    public function test_ca_p01_cancelacion_responde_con_busqueda_y_correo_pendientes(): void
    {
        Queue::fake();
        Mail::fake();
        $d = $this->escenario(false);
        $this->assertNull(IntencionEvaluacionCupo::find($d['intencion'])->procesado_at);
        $this->assertSame(0, EvaluacionAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->count());
        $this->assertSame(0, OfertaAdelanto::where('intencion_evaluacion_cupo_id', $d['intencion'])->count());
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        file_put_contents(base_path('storage/framework/testing/asignacion-cierre-20261117-09/cancelacion-mariadb.json'), json_encode(['ms' => $d['cancelacion_ms'], 'intencion_pendiente' => true, 'evaluaciones' => 0, 'ofertas' => 0, 'correos' => 0]));
    }
}
