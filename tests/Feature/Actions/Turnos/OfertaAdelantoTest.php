<?php

namespace Tests\Feature\Actions\Turnos;

use App\Actions\Agenda\ActualizarConfiguracionAgendaFuturaAction;
use App\Actions\Agenda\AnularConfiguracionAgendaFuturaAction;
use App\Actions\Agenda\CambiarDuracionAgendaAction;
use App\Actions\Agenda\CrearConfiguracionAgendaAction;
use App\Actions\Agenda\RegistrarBloqueoAgendaAction;
use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Actions\Turnos\ActivarOfertaAdelantoAction;
use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\Auditoria;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Turnos\DisponibilidadAgregadaPrimeraConsultaService;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class OfertaAdelantoTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Turno $candidato;

    private IntencionEvaluacionCupo $intencion;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow($this->fecha('2026-11-15 12:00:00'));
        $this->profesional = $this->profesionalUc07();
        $this->candidato = $this->candidato();
        $this->intencion = $this->intencion();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.Auditoria::class);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_activa_cupo_completo_y_conserva_turno_intencion_y_duracion(): void
    {
        $antes = $this->candidato->getAttributes();
        $intencion = $this->intencion->getAttributes();
        $oferta = $this->activar();
        $this->assertNotNull($oferta);
        $persistida = OfertaAdelanto::query()->sole();
        $this->assertSame($this->intencion->id, $persistida->intencion->id);
        $this->assertSame($this->candidato->id, $persistida->turno->id);
        $this->assertSame('10:00:00', $persistida->inicio_at->format('H:i:s'));
        $this->assertSame('10:45:00', $persistida->fin_at->format('H:i:s'));
        $this->assertSame('10:30:00', $persistida->destino_fin_at->format('H:i:s'));
        $this->assertSame(7200.0, $persistida->activada_at->diffInSeconds($persistida->vence_at));
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertSame($intencion, $this->intencion->fresh()->getAttributes());
        $this->assertDatabaseCount('turnos', 2);
        Mail::assertNothingSent();
    }

    #[DataProvider('limitesActivacion')]
    public function test_margen_de_cuatro_horas(string $ahora, bool $admite): void
    {
        CarbonImmutable::setTestNow($this->fecha($ahora));
        $oferta = $this->activar();
        $this->assertSame($admite, $oferta !== null);
        $this->assertDatabaseCount('ofertas_adelanto', $admite ? 1 : 0);
    }

    public static function limitesActivacion(): array
    {
        return [
            ['2026-11-16 06:00:00', true], ['2026-11-16 06:00:01', false],
            ['2026-11-16 06:00:00.000001', false], ['2026-11-16 05:59:59', true],
        ];
    }

    #[DataProvider('tiposCupo')]
    public function test_admite_ambos_tipos_de_cancelacion_y_estados_del_candidato(string $tipo, string $estado): void
    {
        $this->candidato->forceFill(['estado' => $estado])->save();
        $intencion = $this->intencion('11:00:00', '11:45:00', $tipo);
        $this->assertNotNull($this->activar($intencion));
        $this->assertSame($estado, $this->candidato->fresh()->estado->value);
    }

    public static function tiposCupo(): array
    {
        return [['PRIMERA_CONSULTA', 'PENDIENTE'], ['SEGUIMIENTO', 'PENDIENTE'],
            ['PRIMERA_CONSULTA', 'CONFIRMADO'], ['SEGUIMIENTO', 'CONFIRMADO']];
    }

    public function test_repeticion_no_duplica_ni_renueva_la_oferta(): void
    {
        $oferta = $this->activar();
        CarbonImmutable::setTestNow($this->fecha('2026-11-15 12:30:00'));
        $replay = $this->activar();
        $this->assertSame($oferta->id, $replay->id);
        $this->assertSame($oferta->getAttributes(), $replay->getAttributes());
        $this->assertSame(1, Auditoria::query()->where('evento', 'oferta_adelanto_activada')->count());
    }

    public function test_una_oferta_por_paciente_aunque_tenga_otro_turno(): void
    {
        $this->activar();
        $otro = Turno::factory()->for($this->profesional)->for($this->candidato->paciente)->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'PENDIENTE',
            'inicio_at' => '2026-11-22 10:00:00', 'fin_at' => '2026-11-22 10:30:00',
        ]);
        $this->assertNull($this->activar(candidato: $otro));
        $this->assertDatabaseCount('ofertas_adelanto', 1);
    }

    #[DataProvider('crucesReservas')]
    public function test_otra_reserva_respeta_cruces_parciales_totales_y_contiguos(string $inicio, string $fin, bool $admite): void
    {
        $this->activar();
        $otra = $this->intencion($inicio, $fin);
        $this->assertSame($admite, $this->activar($otra, $this->candidato()) !== null);
        $this->assertDatabaseCount('ofertas_adelanto', $admite ? 2 : 1);
    }

    public static function crucesReservas(): array
    {
        return [['10:00:00', '10:45:00', false], ['10:30:00', '11:15:00', false],
            ['10:15:00', '10:45:00', false], ['10:00:00', '11:00:00', false],
            ['10:45:00', '11:30:00', true]];
    }

    #[DataProvider('ocupaciones')]
    public function test_activacion_revalida_destino_y_cupo_completo(string $tipo): void
    {
        if ($tipo === 'bloqueo') {
            BloqueoAgenda::factory()->for($this->profesional)->create([
                'inicio_at' => '2026-11-16 10:35:00', 'fin_at' => '2026-11-16 10:40:00',
            ]);
        } elseif ($tipo === 'turno') {
            Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
                'inicio_at' => '2026-11-16 10:35:00', 'fin_at' => '2026-11-16 11:00:00', 'estado' => 'CONFIRMADO',
            ]);
        } elseif ($tipo === 'paciente') {
            Turno::factory()->for($this->profesionalUc07())->for($this->candidato->paciente)->create([
                'inicio_at' => '2026-11-16 10:15:00', 'fin_at' => '2026-11-16 11:00:00', 'estado' => 'PENDIENTE',
            ]);
        } else {
            $this->profesional->configuracionesAgenda()->update(['anulado_at' => now(), 'motivo_anulacion' => 'Prueba']);
        }
        $this->assertNull($this->activar());
        $this->assertDatabaseCount('ofertas_adelanto', 0);
    }

    public static function ocupaciones(): array
    {
        return [['bloqueo'], ['turno'], ['paciente'], ['configuracion']];
    }

    public function test_limite_exacto_del_vencimiento_en_disponibilidad_individual_y_agregada(): void
    {
        $oferta = $this->activar();
        $this->assertSame(['11:00:00', '11:30:00'], $this->horas());
        CarbonImmutable::setTestNow($oferta->vence_at->subMicrosecond());
        $this->assertSame(['11:00:00', '11:30:00'], $this->horas());
        $this->assertSame(['11:00:00', '11:30:00'], $this->horasAgregadas());
        CarbonImmutable::setTestNow($oferta->vence_at);
        $this->assertSame(['10:00:00', '10:30:00', '11:00:00', '11:30:00'], $this->horas());
        $this->assertSame($this->horas(), $this->horasAgregadas());
        // La reserva deja de bloquear sin depender del futuro cerrador automático.
        $this->assertSame(OfertaAdelanto::ACTIVA, $oferta->fresh()->estado);
    }

    #[DataProvider('altasConReserva')]
    public function test_alta_revalida_reserva_posterior_a_la_consulta(string $hora, bool $admite): void
    {
        $this->assertContains($hora, $this->horas());
        $oferta = $this->activar();
        $paciente = $this->pacienteUc07();
        $antes = $this->candidato->getAttributes();
        if (! $admite) {
            try {
                $this->alta($paciente->user, $hora);
                $this->fail('Debió revalidar la reserva dentro del alta.');
            } catch (SlotNoDisponibleException) {
                $this->assertSame(2, Turno::query()->count());
            }
        } else {
            $resultado = $this->alta($paciente->user, $hora);
            $this->assertSame($hora, $resultado->turno->inicio_at->format('H:i:s'));
        }
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertSame($oferta->getAttributes(), $oferta->fresh()->getAttributes());
    }

    public static function altasConReserva(): array
    {
        return [['10:00:00', false], ['10:30:00', false], ['11:00:00', true]];
    }

    public function test_alta_y_nueva_oferta_permitidas_al_vencimiento_exacto(): void
    {
        $oferta = $this->activar();
        CarbonImmutable::setTestNow($oferta->vence_at);
        $this->assertNotNull($this->activar(candidato: $this->candidato()));
        $this->assertDatabaseCount('ofertas_adelanto', 2);
        // Otra profesional no comparte esa reserva.
        $otra = $this->profesionalUc07();
        $paciente = $this->pacienteUc07();
        $this->assertNotNull(app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
            $paciente->user, (string) Str::uuid(), $otra->id, $this->fecha('2026-11-16 10:00:00'), 'Motivo de prueba'
        ));
    }

    public function test_alta_ignora_reserva_vencida_sin_cerrarla(): void
    {
        $oferta = $this->activar();
        CarbonImmutable::setTestNow($oferta->vence_at);
        $this->assertNotNull($this->alta($this->pacienteUc07()->user, '10:00:00'));
        $this->assertSame(OfertaAdelanto::ACTIVA, $oferta->fresh()->estado);
    }

    public function test_disponibilidad_y_alta_admiten_contiguidad_exacta_con_el_cupo(): void
    {
        $this->profesional->configuracionesAgenda()->update(['duracion_turno_minutos' => 15]);
        $this->activar();
        $this->assertNotContains('10:30:00', $this->horas());
        $this->assertContains('10:45:00', $this->horas());
        $this->assertContains('10:45:00', $this->horasAgregadas());
        $resultado = $this->alta($this->pacienteUc07()->user, '10:45:00');
        $this->assertSame('11:00:00', $resultado->turno->fin_at->format('H:i:s'));
    }

    public function test_reserva_fuera_de_grilla_excluye_ambos_slots_parcialmente_superpuestos(): void
    {
        $intencion = $this->intencion('10:10:00', '10:55:00');
        $oferta = $this->activar($intencion);
        $this->assertNotNull($oferta);
        $this->assertSame('10:40:00', $oferta->destino_fin_at->format('H:i:s'));
        $this->assertSame(['11:00:00', '11:30:00'], $this->horas());
        $this->assertSame($this->horas(), $this->horasAgregadas());
    }

    public function test_activacion_usa_orden_canonico_y_lecturas_actuales_bloqueantes(): void
    {
        $registro = $this->registrarLecturas();
        $nivel = DB::transactionLevel();
        $this->assertNotNull(app(ActivarOfertaAdelantoAction::class)->ejecutar($this->intencion->id, $this->candidato->id));
        $lecturas = array_values(array_filter($registro->lecturas, fn (array $fila): bool => $fila['lock'] === true));
        $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], array_slice(array_column($lecturas, 'tabla'), 0, 3));
        foreach (['intenciones_evaluacion_cupo', 'ofertas_adelanto', 'configuraciones_agenda', 'franjas_horarias_agenda', 'bloqueos_agenda'] as $tabla) {
            $this->assertContains($tabla, array_column($lecturas, 'tabla'));
        }
        foreach ($lecturas as $lectura) {
            $this->assertSame($nivel + 1, $lectura['nivel']);
            $this->assertStringEndsWith('for update', $lectura['sql']);
        }
    }

    public function test_activacion_revalida_reloj_despues_de_leer_las_reservas(): void
    {
        CarbonImmutable::setTestNow($this->fecha('2026-11-16 05:59:59'));
        $registro = $this->registrarLecturas();
        $registro->alLeer = function (QueryBuilder $consulta): void {
            if ($consulta->from === 'ofertas_adelanto' && count($consulta->wheres) > 1) {
                CarbonImmutable::setTestNow($this->fecha('2026-11-16 06:00:01'));
            }
        };
        $this->assertNull($this->activar());
        $this->assertDatabaseCount('ofertas_adelanto', 0);
    }

    public function test_alta_evalua_vencimiento_con_reloj_posterior_a_las_lecturas(): void
    {
        $oferta = $this->activar();
        $paciente = $this->pacienteUc07();
        CarbonImmutable::setTestNow($oferta->vence_at->subSecond());
        $registro = $this->registrarLecturas();
        $registro->alLeer = function (QueryBuilder $consulta) use ($oferta): void {
            if ($consulta->from === 'ofertas_adelanto' && $consulta->lock === true) {
                CarbonImmutable::setTestNow($oferta->vence_at);
            }
        };
        $this->assertNotNull($this->alta($paciente->user, '10:00:00'));
    }

    #[DataProvider('bloqueos')]
    public function test_bloqueo_cierra_solo_si_invalida_y_no_modifica_turnos(string $inicio, string $fin, bool $cierra): void
    {
        $oferta = $this->activar();
        $antes = $this->candidato->getAttributes();
        $this->bloquear($inicio, $fin);
        $this->assertSame($cierra ? OfertaAdelanto::CERRADA_AGENDA : OfertaAdelanto::ACTIVA, $oferta->fresh()->estado);
        $this->assertTrue($oferta->vence_at->equalTo($oferta->fresh()->vence_at));
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        if ($cierra) {
            $this->assertNotContains('10:00:00', $this->horas());
            $this->assertContains('10:30:00', $this->horas());
        }
    }

    public static function bloqueos(): array
    {
        return [['10:10:00', '10:20:00', true], ['10:30:00', '11:00:00', false],
            ['11:00:00', '11:30:00', false]];
    }

    public function test_bloqueo_sobre_turno_existente_preserva_advertencia_y_programacion(): void
    {
        $oferta = $this->activar();
        $antes = $this->candidato->getAttributes();
        $actor = $this->profesional->user;
        $data = ['inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 11:00:00', 'motivo' => 'Bloqueo del origen'];
        $revision = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $data);
        $this->assertSame(1, $revision['cantidad_turnos_afectados']);
        $this->assertSame($this->candidato->id, $revision['turnos_afectados'][0]['id']);
        app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $data + ['snapshot' => $revision['snapshot']]);
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertSame($oferta->getAttributes(), $oferta->fresh()->getAttributes());
    }

    #[DataProvider('cambiosConfiguracion')]
    public function test_todas_las_operaciones_de_configuracion_revalidan_sin_recalcular_el_turno(string $operacion, bool $compatible): void
    {
        $config = $this->prepararConfiguracionFutura($operacion, $compatible);
        $oferta = $this->activar();
        $antes = $this->candidato->getAttributes();
        $this->cambiarConfiguracion($operacion, $compatible, $config);
        $actual = $oferta->fresh();
        $this->assertSame($compatible ? OfertaAdelanto::ACTIVA : OfertaAdelanto::CERRADA_AGENDA, $actual->estado);
        $this->assertTrue($oferta->vence_at->equalTo($actual->vence_at));
        $this->assertSame($antes, $this->candidato->fresh()->getAttributes());
        $this->assertSame('10:30:00', $actual->destino_fin_at->format('H:i:s'));
    }

    public static function cambiosConfiguracion(): array
    {
        return [['crear', false], ['crear', true], ['actualizar', false], ['actualizar', true],
            ['duracion', false], ['duracion', true], ['anular', false], ['anular', true]];
    }

    #[DataProvider('cambiosConFallo')]
    public function test_fallo_revierte_juntos_cambio_cierre_y_auditoria(string $cambio): void
    {
        $oferta = $this->activar();
        $configAntes = DB::table('configuraciones_agenda')->get()->toJson();
        $franjasAntes = DB::table('franjas_horarias_agenda')->get()->toJson();
        $auditorias = Auditoria::query()->count();
        Auditoria::creating(function (Auditoria $registro): void {
            if ($registro->evento === 'oferta_adelanto_cerrada_por_agenda') {
                throw new RuntimeException('Fallo sintético posterior al cierre.');
            }
        });
        try {
            if ($cambio === 'bloqueo') {
                $this->bloquear('10:10:00', '10:20:00');
            } else {
                $this->cambiarConfiguracion('crear', false, null);
            }
            $this->fail('Debió revertir la transacción completa.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fallo sintético posterior al cierre.', $error->getMessage());
        }
        $this->assertSame($oferta->getAttributes(), $oferta->fresh()->getAttributes());
        $this->assertSame($configAntes, DB::table('configuraciones_agenda')->get()->toJson());
        $this->assertSame($franjasAntes, DB::table('franjas_horarias_agenda')->get()->toJson());
        $this->assertSame($auditorias, Auditoria::query()->count());
        $this->assertDatabaseCount('bloqueos_agenda', 0);
    }

    public static function cambiosConFallo(): array
    {
        return [['bloqueo'], ['configuracion']];
    }

    public function test_activacion_y_reserva_revierten_si_falla_la_auditoria(): void
    {
        Auditoria::creating(function (Auditoria $registro): void {
            if ($registro->evento === 'oferta_adelanto_activada') {
                throw new RuntimeException('Fallo de auditoría');
            }
        });
        try {
            $this->activar();
            $this->fail('Debió revertir la oferta.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('ofertas_adelanto', 0);
        }
        $this->assertContains('10:00:00', $this->horas());
    }

    public function test_identidad_y_vencimiento_no_se_pueden_reescribir(): void
    {
        $oferta = $this->activar();
        $this->expectException(LogicException::class);
        $oferta->update(['vence_at' => $oferta->vence_at->addHour()]);
    }

    private function activar(?IntencionEvaluacionCupo $intencion = null, ?Turno $candidato = null): ?OfertaAdelanto
    {
        return app(ActivarOfertaAdelantoAction::class)->ejecutar(($intencion ?? $this->intencion)->id, ($candidato ?? $this->candidato)->id)?->fresh();
    }

    private function candidato(): Turno
    {
        return Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'PENDIENTE',
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00',
        ])->fresh();
    }

    private function intencion(string $inicio = '10:00:00', string $fin = '10:45:00', string $tipo = 'SEGUIMIENTO'): IntencionEvaluacionCupo
    {
        $ahora = CarbonImmutable::now();
        CarbonImmutable::setTestNow($this->fecha('2026-11-15 08:00:00'));
        $paciente = $this->pacienteUc07();
        $turno = Turno::factory()->for($this->profesional)->for($paciente)->create([
            'tipo' => $tipo, 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-16 '.$inicio, 'fin_at' => '2026-11-16 '.$fin,
        ])->fresh();
        app(CancelarTurnoAction::class)->ejecutar($paciente->user, $turno);
        CarbonImmutable::setTestNow($ahora);

        return IntencionEvaluacionCupo::query()->where('turno_id', $turno->id)->sole();
    }

    private function fecha(string $fecha): CarbonImmutable
    {
        return CarbonImmutable::parse($fecha, config('app.timezone'));
    }

    private function horas(): array
    {
        return array_map(fn (array $slot): string => $slot['inicio_at']->format('H:i:s'),
            app(DisponibilidadPrimeraConsultaService::class)->consultarDisponibilidad(
                $this->profesional, $this->fecha('2026-11-16'), $this->fecha('2026-11-16')
            ));
    }

    private function horasAgregadas(): array
    {
        $dias = app(DisponibilidadAgregadaPrimeraConsultaService::class)->consultarDisponibilidad(
            $this->fecha('2026-11-16'), $this->fecha('2026-11-16')
        );

        return array_map(fn (array $horario): string => $horario['inicio_at']->format('H:i:s'), $dias[0]['horarios'] ?? []);
    }

    private function alta(User $actor, string $hora): mixed
    {
        return app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
            $actor, (string) Str::uuid(), $this->profesional->id, $this->fecha('2026-11-16 '.$hora), 'Motivo de prueba'
        );
    }

    private function bloquear(string $inicio, string $fin): void
    {
        $actor = $this->profesional->user;
        $data = ['inicio_at' => '2026-11-16 '.$inicio, 'fin_at' => '2026-11-16 '.$fin, 'motivo' => 'Bloqueo de prueba'];
        $revision = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $data);
        app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $data + ['snapshot' => $revision['snapshot']]);
    }

    private function prepararConfiguracionFutura(string $operacion, bool $compatible): ?ConfiguracionAgenda
    {
        if (! in_array($operacion, ['actualizar', 'anular'], true)) {
            return null;
        }
        $previa = $this->profesional->configuracionesAgenda()->sole();
        $previa->update(['vigencia_hasta' => '2026-11-15']);
        if ($operacion === 'anular' && ! $compatible) {
            $previa->franjasHorarias()->update(['hora_inicio' => '11:00:00']);
        }
        $config = ConfiguracionAgenda::factory()->for($this->profesional)->create([
            'vigencia_desde' => '2026-11-16', 'vigencia_hasta' => null, 'duracion_turno_minutos' => 30,
        ]);
        $config->franjasHorarias()->create(['dia_semana' => 1, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00']);

        return $config;
    }

    private function cambiarConfiguracion(string $operacion, bool $compatible, ?ConfiguracionAgenda $config): void
    {
        $actor = $this->profesional->user;
        $data = ['vigencia_desde' => '2026-11-16', 'vigencia_hasta' => null, 'duracion_turno_minutos' => 45,
            'franjas' => [['dia_semana' => 1, 'hora_inicio' => $compatible ? '10:00:00' : '11:00:00', 'hora_fin' => '12:00:00']]];
        if ($operacion === 'crear') {
            app(CrearConfiguracionAgendaAction::class)->ejecutar($actor, $data);
        } elseif ($operacion === 'duracion') {
            $origen = $this->profesional->configuracionesAgenda()->sole();
            $data['snapshot'] = app(AgendaSnapshotService::class)->generarParaConfiguracion($origen);
            app(CambiarDuracionAgendaAction::class)->ejecutar($actor, $data);
        } else {
            $data['snapshot'] = app(AgendaSnapshotService::class)->generarParaConfiguracion($config);
            if ($operacion === 'actualizar') {
                app(ActualizarConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $config, $data);
            } else {
                app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($actor, $config, $data + ['motivo_anulacion' => 'Prueba']);
            }
        }
    }

    private function registrarLecturas(): SQLiteGrammar
    {
        $conexion = DB::connection();
        $registro = new class($conexion) extends SQLiteGrammar
        {
            public array $lecturas = [];

            public ?Closure $alLeer = null;

            public function compileSelect(QueryBuilder $query)
            {
                $this->lecturas[] = [
                    'tabla' => $query->from, 'lock' => $query->lock,
                    'nivel' => $query->getConnection()->transactionLevel(),
                    // Sólo compilación de SQL; no se abre ninguna conexión MySQL.
                    'sql' => (new MySqlGrammar($query->getConnection()))->compileSelect(clone $query),
                ];
                if ($this->alLeer !== null) {
                    ($this->alLeer)($query);
                }

                return parent::compileSelect($query);
            }
        };
        $conexion->setQueryGrammar($registro);

        return $registro;
    }
}
