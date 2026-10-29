<?php

namespace Tests\Feature\Actions\Turnos;

use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Actions\Turnos\ResultadoConfirmacionPrimeraConsulta;
use App\Enums\DiaSemanaEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ClaveIdempotenciaIncompatibleException;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\Auditoria;
use App\Models\BloqueoAgenda;
use App\Models\ColegioEmisor;
use App\Models\ConfiguracionAgenda;
use App\Models\EspecialidadKinesiologia;
use App\Models\FranjaHorariaAgenda;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfirmarPrimeraConsultaActionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private ConfiguracionAgenda $configuracion;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 09:00:00'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        $this->actor = User::factory()->create();
        $this->paciente = Paciente::create(['user_id' => $this->actor->id, 'activo' => true]);
        $this->kinesiologa = $this->profesional();
        $this->configuracion = ConfiguracionAgenda::factory()->for($this->kinesiologa)->create([
            'vigencia_desde' => '2026-10-01', 'duracion_turno_minutos' => 30,
        ]);
        FranjaHorariaAgenda::factory()->for($this->configuracion, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00',
        ]);
        $this->uuid = (string) Str::uuid();
        Storage::shouldReceive('disk')->never();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        DB::disableQueryLog();
        Event::forget('eloquent.creating: '.Turno::class);
        Event::forget('eloquent.creating: '.Auditoria::class);
        Event::forget(TransactionRolledBack::class);
        parent::tearDown();
    }

    public function test_creates_first_consultation_with_internal_type_state_uuid_reason_and_derived_end(): void
    {
        $resultado = $this->confirmar();
        $this->assertInstanceOf(ResultadoConfirmacionPrimeraConsulta::class, $resultado);
        $this->assertFalse($resultado->esReplay);
        $turno = $resultado->turno->fresh();
        $this->assertSame($this->paciente->id, $turno->paciente_id);
        $this->assertSame($this->kinesiologa->id, $turno->kinesiologa_id);
        $this->assertSame(TipoTurnoEnum::PRIMERA_CONSULTA, $turno->tipo);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $turno->estado);
        $this->assertSame($this->uuid, $turno->clave_idempotencia);
        $this->assertSame('Motivo original clínico', $turno->motivo_solicitud);
        $this->assertSame('2026-10-05 10:00:00', $turno->inicio_at->toDateTimeString());
        $this->assertSame('2026-10-05 10:30:00', $turno->fin_at->toDateTimeString());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('documentos_clinicos', 0);
        $this->assertDatabaseCount('documentos_clinicos_origen_turno', 0);
    }

    public function test_creation_audits_inside_transaction_with_operational_whitelist_only(): void
    {
        $niveles = [];
        Auditoria::creating(function () use (&$niveles): void {
            $niveles[] = DB::transactionLevel();
        });
        $base = DB::transactionLevel();
        $resultado = $this->confirmar();
        $auditoria = Auditoria::query()->sole();
        $this->assertSame([$base + 1], $niveles);
        $this->assertSame('turno_primera_consulta_creado', $auditoria->evento);
        $this->assertSame('Turnos', $auditoria->modulo);
        $this->assertSame($this->actor->id, $auditoria->actor_user_id);
        $this->assertSame($resultado->turno->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($resultado->turno->id, $auditoria->auditable_id);
        $this->assertSame([
            'turno_id', 'paciente_id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at',
        ], array_keys($auditoria->valores_nuevos));
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertStringNotContainsString('Motivo original clínico', json_encode($auditoria->toArray()));
        $this->assertArrayNotHasKey('motivo_solicitud', $auditoria->valores_nuevos);
        $this->assertArrayNotHasKey('adjuntos', $auditoria->valores_nuevos);
        $this->assertArrayNotHasKey('nombre_original', $auditoria->valores_nuevos);
    }

    public function test_immediate_replay_needs_only_identity_and_uuid_and_does_not_start_an_action_transaction(): void
    {
        $primero = $this->confirmar();
        $registro = $this->registrarLecturas();
        $nivel = DB::transactionLevel();
        $replay = app(ConfirmarPrimeraConsultaAction::class)->ejecutar($this->actor, $this->uuid);
        $this->assertTrue($replay->esReplay);
        $this->assertTrue($primero->turno->is($replay->turno));
        foreach ($registro->lecturas as $lectura) {
            $this->assertSame($nivel, $lectura['nivel']);
            $this->assertFalse((bool) $lectura['lock']);
        }
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_replay_ignores_current_professional_slot_reason_files_and_all_mutable_creation_conditions(): void
    {
        $creado = $this->confirmar()->turno;
        $original = $creado->fresh()->getRawOriginal();
        $this->kinesiologa->user->update(['activo' => false]);
        CarbonImmutable::setTestNow($this->fecha('2027-01-01 12:00:00'));

        $replay = app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
            $this->actor, strtoupper($this->uuid), -999, $this->fecha('2000-01-01'), '', [new \stdClass]
        );
        $this->assertTrue($replay->esReplay);
        $this->assertSame($original, $replay->turno->getRawOriginal());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_replay_is_not_blocked_by_its_own_active_first_consultation_or_new_occupancy(): void
    {
        $creado = $this->confirmar()->turno;
        $this->turno(paciente: $this->otroPaciente());
        $replay = $this->confirmar();
        $this->assertTrue($replay->esReplay);
        $this->assertSame($creado->id, $replay->turno->id);
        $this->assertDatabaseCount('turnos', 2);
        $this->assertDatabaseCount('auditorias', 1);
    }

    #[DataProvider('contextosIncompatibles')]
    public function test_incompatible_uuid_context_is_rejected_without_disclosing_owner_or_appointment(bool $otroPaciente, TipoTurnoEnum $tipo): void
    {
        $this->turno($otroPaciente ? $this->otroPaciente() : $this->paciente, tipo: $tipo, uuid: $this->uuid);
        try {
            $this->confirmar();
            $this->fail('Debe rechazar la clave incompatible.');
        } catch (ClaveIdempotenciaIncompatibleException $error) {
            $this->assertSame('La clave de intención no puede utilizarse.', $error->getMessage());
            $this->assertStringNotContainsString($this->uuid, $error->getMessage());
        }
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function contextosIncompatibles(): array
    {
        return [[true, TipoTurnoEnum::PRIMERA_CONSULTA], [false, TipoTurnoEnum::SEGUIMIENTO]];
    }

    public function test_documental_creation_cannot_finalize_an_outer_transactions_savepoint(): void
    {
        $registro = $this->registrarLecturas();
        try {
            $this->confirmar(adjuntos: ['archivo no admitido en 7.11']);
            $this->fail('El ciclo documental debe ser propietario del commit.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('transacción exterior', $error->getMessage());
        }
        $this->assertNotEmpty(array_filter($registro->lecturas, fn ($lectura) => $lectura['lock']));
        $this->assertDatabaseCount('turnos', 0);
        $this->assertDatabaseCount('documentos_clinicos', 0);
    }

    public function test_actor_cannot_supply_an_unrelated_patient_or_spoof_a_cached_relation(): void
    {
        $this->actor->setRelation('paciente', $this->otroPaciente());
        $this->assertSame($this->paciente->id, $this->confirmar()->turno->paciente_id);
        $sinPaciente = User::factory()->create();
        $sinPaciente->setRelation('paciente', $this->paciente);
        $this->expectException(InvalidArgumentException::class);
        app(ConfirmarPrimeraConsultaAction::class)->ejecutar($sinPaciente, (string) Str::uuid());
    }

    public function test_d01_is_checked_before_professional_and_slot_conflicts(): void
    {
        $this->turno($this->paciente, tipo: TipoTurnoEnum::PRIMERA_CONSULTA);
        $this->kinesiologa->user->update(['activo' => false]);
        $this->expectException(PrimeraConsultaActivaException::class);
        $this->confirmar(inicio: $this->fecha('2026-10-05 10:15:00'));
    }

    public function test_professional_conflict_has_priority_over_slot_conflict(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->turno();
        $this->expectException(ProfesionalNoElegibleException::class);
        $this->confirmar();
    }

    #[DataProvider('slotsInvalidos')]
    public function test_invalid_or_expired_slot_is_rejected(string $inicio): void
    {
        $this->expectException(SlotNoDisponibleException::class);
        $this->confirmar(inicio: $this->fecha($inicio));
    }

    public static function slotsInvalidos(): array
    {
        return [
            'fuera de grilla' => ['2026-10-05 10:15:00'],
            'sin franja' => ['2026-10-06 10:00:00'],
            'sin configuracion' => ['2026-09-28 10:00:00'],
            'microsegundos' => ['2026-10-05 10:00:00.000001'],
        ];
    }

    public function test_start_at_authoritative_now_is_rejected_without_buffer(): void
    {
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 10:00:00'));
        $this->expectException(SlotNoDisponibleException::class);
        $this->confirmar();
    }

    public function test_past_start_is_rejected_even_if_its_end_is_still_future(): void
    {
        CarbonImmutable::setTestNow($this->fecha('2026-10-05 10:00:01'));
        $this->expectException(SlotNoDisponibleException::class);
        $this->confirmar();
    }

    #[DataProvider('estadosOcupantes')]
    public function test_current_occupancy_follows_canonical_appointment_states(EstadoTurnoEnum $estado, bool $ocupa): void
    {
        $this->turno(estado: $estado);
        if ($ocupa) {
            $this->expectException(SlotNoDisponibleException::class);
        }
        $resultado = $this->confirmar();
        $this->assertFalse($resultado->esReplay);
    }

    public static function estadosOcupantes(): array
    {
        return [
            'Pendiente' => [EstadoTurnoEnum::PENDIENTE, true],
            'Confirmado' => [EstadoTurnoEnum::CONFIRMADO, true],
            'En atención' => [EstadoTurnoEnum::EN_ATENCION, true],
            'Atendido' => [EstadoTurnoEnum::ATENDIDO, false],
            'Ausente' => [EstadoTurnoEnum::AUSENTE, false],
            'Cancelado' => [EstadoTurnoEnum::CANCELADO, false],
        ];
    }

    public function test_in_attention_first_consultation_preserves_d01_and_blocks_only_its_interval(): void
    {
        $existente = $this->turno($this->paciente, tipo: TipoTurnoEnum::PRIMERA_CONSULTA, estado: EstadoTurnoEnum::EN_ATENCION);

        try {
            $this->confirmar();
            $this->fail('El intervalo de la atención en curso debe seguir ocupado.');
        } catch (SlotNoDisponibleException) {
            $this->assertDatabaseCount('turnos', 1);
            $this->assertDatabaseCount('auditorias', 0);
        }

        $resultado = $this->confirmar(inicio: $this->fecha('2026-10-05 10:30:00'));

        $this->assertFalse($resultado->esReplay);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $resultado->turno->estado);
        $this->assertSame('2026-10-05 10:30:00', $resultado->turno->inicio_at->toDateTimeString());
        $this->assertSame(EstadoTurnoEnum::EN_ATENCION, $existente->fresh()->estado);
        $this->assertDatabaseCount('turnos', 2);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_effective_block_prevents_confirmation(): void
    {
        $this->bloqueo();
        $this->expectException(SlotNoDisponibleException::class);
        $this->confirmar();
    }

    public function test_annulled_block_and_other_professionals_occupancy_do_not_prevent_confirmation(): void
    {
        $bloqueo = $this->bloqueo();
        $bloqueo->forceFill(['anulado_at' => $this->fecha('2026-10-05 08:00:00'), 'motivo_anulacion' => 'Prueba'])->save();
        $otra = $this->profesional();
        $this->turno(kinesiologa: $otra);
        $this->bloqueo($otra);
        $this->assertFalse($this->confirmar()->esReplay);
    }

    public function test_agenda_changes_since_visualization_are_recalculated(): void
    {
        $motor = app(DisponibilidadPrimeraConsultaService::class);
        $inicio = $this->fecha('2026-10-05 10:00:00');
        $this->assertNotNull($motor->revalidarSlot($this->kinesiologa, $inicio));
        $this->configuracion->franjasHorarias()->update(['hora_inicio' => '10:10:00']);
        $this->expectException(SlotNoDisponibleException::class);
        $this->confirmar();
    }

    public function test_audit_failure_rolls_back_the_appointment_and_propagates_original_error(): void
    {
        $original = new RuntimeException('Falla requerida de auditoría.');
        Auditoria::creating(function () use ($original): never {
            throw $original;
        });
        try {
            $this->confirmar();
            $this->fail('Debe propagar la falla.');
        } catch (RuntimeException $error) {
            $this->assertSame($original, $error);
        }
        $this->assertDatabaseCount('turnos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_internal_agenda_integrity_error_is_not_replaced_by_a_slot_conflict(): void
    {
        ConfiguracionAgenda::factory()->for($this->kinesiologa)->create(['vigencia_desde' => '2026-10-01']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('más de una configuración');
        $this->confirmar();
    }

    #[DataProvider('motivosInvalidos')]
    public function test_direct_creation_protects_required_reason_and_length_invariants(?string $motivo): void
    {
        try {
            $this->confirmar(motivo: $motivo);
            $this->fail('Debe rechazar el motivo.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('turnos', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public static function motivosInvalidos(): array
    {
        return [[null], ['   '], [str_repeat('a', 501)]];
    }

    public function test_unexpected_sql_error_is_not_converted_to_replay_or_functional_conflict(): void
    {
        $original = $this->errorSql(['HY000', 1, 'Error SQL inesperado']);
        Turno::creating(function () use ($original): never {
            throw $original;
        });
        try {
            $this->confirmar();
            $this->fail('Debe propagarse el error SQL.');
        } catch (QueryException $error) {
            $this->assertSame($original, $error);
        }
        $this->assertDatabaseCount('turnos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_parent_order_uuid_recheck_and_all_critical_reads_are_built_with_for_update(): void
    {
        $base = DB::transactionLevel();
        $registro = $this->registrarLecturas();
        $this->confirmar();
        $lecturas = $registro->lecturas;
        $bloqueantes = array_values(array_filter($lecturas, fn ($fila) => $fila['lock'] === true));
        $this->assertSame(['kinesiologas', 'pacientes', 'turnos', 'turnos'], array_slice(array_column($bloqueantes, 'tabla'), 0, 4));
        $this->assertStringContainsString('clave_idempotencia', $bloqueantes[2]['sql']);
        $this->assertStringContainsString('paciente_id', $bloqueantes[3]['sql']);
        $this->assertStringContainsString('inicio_at', $bloqueantes[3]['sql']);
        foreach ([
            'users', 'model_has_roles', 'roles', 'matriculas_profesionales_kinesiologia',
            'kinesiologa_especialidad', 'configuraciones_agenda', 'franjas_horarias_agenda', 'bloqueos_agenda',
        ] as $tabla) {
            $this->assertContains($tabla, array_column($bloqueantes, 'tabla'));
        }
        $this->assertCount(3, array_filter($bloqueantes, fn ($fila) => $fila['tabla'] === 'turnos'));
        $dentro = false;
        foreach ($lecturas as $lectura) {
            if ($lectura['lock']) {
                $dentro = true;
                $this->assertSame($base + 1, $lectura['nivel']);
                $this->assertStringEndsWith('for update', $lectura['sql']);
            }
            if ($dentro) {
                $this->assertTrue($lectura['lock'], 'No deben aparecer SELECT normales ocultos después de bloquear padres.');
            }
        }
    }

    public function test_transactional_recheck_returns_replay_that_appears_after_the_initial_check(): void
    {
        $registro = $this->registrarLecturas();
        $aparecido = null;
        $registro->alLeer = function (QueryBuilder $query) use (&$aparecido): void {
            if ($query->from === 'kinesiologas' && $query->lock === true && $aparecido === null) {
                // Seam secuencial: representa visibilidad nueva; no simula concurrencia física SQLite.
                $aparecido = $this->turno($this->paciente, tipo: TipoTurnoEnum::PRIMERA_CONSULTA, uuid: $this->uuid);
            }
        };
        $resultado = $this->confirmar();
        $this->assertTrue($resultado->esReplay);
        $this->assertSame($aparecido->id, $resultado->turno->id);
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_transactional_recheck_rejects_new_incompatible_context_before_d01(): void
    {
        $registro = $this->registrarLecturas();
        $otro = $this->otroPaciente();
        $aparecido = false;
        $registro->alLeer = function (QueryBuilder $query) use (&$aparecido, $otro): void {
            if ($query->from === 'kinesiologas' && $query->lock === true && ! $aparecido) {
                $aparecido = true;
                $this->turno($otro, tipo: TipoTurnoEnum::PRIMERA_CONSULTA, uuid: $this->uuid);
            }
        };
        $this->expectException(ClaveIdempotenciaIncompatibleException::class);
        $this->confirmar();
    }

    public function test_authoritative_clock_is_sampled_after_occupancy_and_blocking_reads(): void
    {
        $registro = $this->registrarLecturas();
        $registro->alLeer = function (QueryBuilder $query): void {
            if ($query->from === 'bloqueos_agenda' && $query->lock === true) {
                CarbonImmutable::setTestNow($this->fecha('2026-10-05 10:00:00'));
            }
        };
        $this->expectException(SlotNoDisponibleException::class);
        $this->confirmar();
    }

    public function test_loaded_authoritative_eligibility_and_availability_execute_zero_hidden_selects(): void
    {
        $kinesiologa = Kinesiologa::query()->whereKey($this->kinesiologa->id)->lockForUpdate()->firstOrFail();
        $elegibilidad = app(ElegibilidadKinesiologaService::class);
        $elegibilidad->prepararEvaluacionBloqueante($kinesiologa);
        $agenda = app(AgendaQueryService::class);
        $inicio = $this->fecha('2026-10-05 10:00:00');
        $configuracion = $agenda->seleccionarConfiguracionAplicable($agenda->configuracionesAplicablesEnQuery($kinesiologa, $inicio)->lockForUpdate()->get());
        $configuracion->setRelation('franjasHorarias', $agenda->franjasDe($configuracion)->lockForUpdate()->get());
        $turnos = $agenda->turnosAfectadosPorIntervalo($kinesiologa, $inicio, $inicio->addHours(2))->lockForUpdate()->get();
        $bloqueos = $agenda->bloqueosSuperpuestosCon($kinesiologa, $inicio, $inicio->addHours(2))->lockForUpdate()->get();
        $motor = app(DisponibilidadPrimeraConsultaService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $esElegible = $elegibilidad->esElegible($kinesiologa);
        $grilla = $motor->generarGrillaDesdeConfiguracion($configuracion, $inicio);
        $disponibles = $motor->evaluarCandidatosDisponibles($grilla, $turnos, $bloqueos, app(RelojAplicacion::class)->ahora(), $esElegible);

        $this->assertTrue($esElegible);
        $this->assertCount(4, $disponibles);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_stale_professional_relations_do_not_override_current_locked_profile(): void
    {
        $this->kinesiologa->load(['user.roles', 'matriculasProfesionales', 'especialidades']);
        $this->kinesiologa->matriculasProfesionales()->update(['activa' => false]);
        $this->expectException(ProfesionalNoElegibleException::class);
        $this->confirmar();
    }

    #[DataProvider('erroresDeIntegridad')]
    public function test_only_the_specific_idempotency_unique_constraint_is_classified(array $info, bool $esUuid): void
    {
        $metodo = new ReflectionMethod(ConfirmarPrimeraConsultaAction::class, 'esColisionClaveIdempotencia');
        $this->assertSame($esUuid, $metodo->invoke(app(ConfirmarPrimeraConsultaAction::class), $this->errorSql($info)));
    }

    public static function erroresDeIntegridad(): array
    {
        return [
            'SQLite UUID' => [['23000', 19, 'UNIQUE constraint failed: turnos.clave_idempotencia'], true],
            'SQLite extendido' => [['23000', 2067, 'UNIQUE constraint failed: turnos.clave_idempotencia'], true],
            'MariaDB UUID' => [['23000', 1062, "Duplicate entry 'uuid' for key 'turnos_clave_idempotencia_unique'"], true],
            'MySQL UUID calificada' => [['23000', 1062, "Duplicate entry 'uuid' for key 'turnos.turnos_clave_idempotencia_unique'"], true],
            'FK' => [['23000', 19, 'FOREIGN KEY constraint failed'], false],
            'NOT NULL' => [['23000', 19, 'NOT NULL constraint failed: turnos.estado'], false],
            'otra UNIQUE SQLite' => [['23000', 19, 'UNIQUE constraint failed: turnos.id'], false],
            'otra UNIQUE MariaDB' => [['23000', 1062, "Duplicate entry 'turnos_clave_idempotencia_unique' for key 'otra_unique'"], false],
            'otra columna adicional' => [['23000', 19, 'UNIQUE constraint failed: turnos.clave_idempotencia, turnos.id'], false],
            'SQL desconocido' => [['HY000', 1, 'Error desconocido'], false],
            'sin informacion' => [[], false],
        ];
    }

    #[DataProvider('contextosDeColision')]
    public function test_uuid_collision_is_rechecked_only_after_rollback_and_never_retries_the_callback(bool $ajeno): void
    {
        $original = $this->errorSql(['23000', 19, 'UNIQUE constraint failed: turnos.clave_idempotencia']);
        $intentos = 0;
        $fallo = false;
        $ganador = null;
        $propietario = $ajeno ? $this->otroPaciente() : $this->paciente;
        $nivelInicial = DB::transactionLevel();
        Turno::creating(function () use (&$fallo, &$intentos, $original): void {
            if (! $fallo) {
                $intentos++;
                $fallo = true;
                throw $original;
            }
        });
        Event::listen(TransactionRolledBack::class, function ($evento) use (&$ganador, $propietario, $nivelInicial): void {
            $this->assertSame($nivelInicial, $evento->connection->transactionLevel());
            $ganador = $this->turno($propietario, tipo: TipoTurnoEnum::PRIMERA_CONSULTA, uuid: $this->uuid);
        });

        if ($ajeno) {
            try {
                $this->confirmar();
                $this->fail('La recuperación no puede devolver un turno ajeno.');
            } catch (ClaveIdempotenciaIncompatibleException $error) {
                $this->assertSame('La clave de intención no puede utilizarse.', $error->getMessage());
            }
        } else {
            $resultado = $this->confirmar();
            $this->assertTrue($resultado->esReplay);
            $this->assertSame($ganador->id, $resultado->turno->id);
        }
        $this->assertSame(1, $intentos);
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function contextosDeColision(): array
    {
        return [[false], [true]];
    }

    public function test_uuid_unique_without_a_committed_result_still_propagates_the_original_error(): void
    {
        $original = $this->errorSql(['23000', 19, 'UNIQUE constraint failed: turnos.clave_idempotencia']);
        Turno::creating(function () use ($original): never {
            throw $original;
        });
        try {
            $this->confirmar();
            $this->fail('No debe inventar un replay sin fila consolidada.');
        } catch (QueryException $error) {
            $this->assertSame($original, $error);
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
                    'tabla' => $query->from,
                    'lock' => $query->lock,
                    'nivel' => $query->getConnection()->transactionLevel(),
                    // Sólo compilar: este SQL MySQL jamás se ejecuta en una conexión real.
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

    private function confirmar(?CarbonImmutable $inicio = null, ?string $motivo = 'Motivo original clínico', array $adjuntos = []): ResultadoConfirmacionPrimeraConsulta
    {
        return app(ConfirmarPrimeraConsultaAction::class)->ejecutar(
            $this->actor, $this->uuid, $this->kinesiologa->id,
            $inicio ?? $this->fecha('2026-10-05 10:00:00'), $motivo, $adjuntos,
        );
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('app.timezone'));
    }

    private function errorSql(array $info): QueryException
    {
        $causa = new PDOException($info[2] ?? 'Error sin información');
        $causa->errorInfo = $info;

        return new QueryException('sqlite', 'insert into turnos (...) values (...)', [], $causa);
    }

    private function otroPaciente(): Paciente
    {
        return Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
    }

    private function turno(?Paciente $paciente = null, ?Kinesiologa $kinesiologa = null, TipoTurnoEnum $tipo = TipoTurnoEnum::SEGUIMIENTO, EstadoTurnoEnum $estado = EstadoTurnoEnum::PENDIENTE, ?string $uuid = null): Turno
    {
        $turno = Turno::factory()->for($paciente ?? $this->otroPaciente())->for($kinesiologa ?? $this->kinesiologa)->make([
            'tipo' => $tipo, 'estado' => $estado,
            'inicio_at' => $this->fecha('2026-10-05 10:00:00'), 'fin_at' => $this->fecha('2026-10-05 10:30:00'),
        ]);
        $turno->clave_idempotencia = $uuid;
        $turno->save();

        return $turno;
    }

    private function bloqueo(?Kinesiologa $kinesiologa = null): BloqueoAgenda
    {
        return BloqueoAgenda::factory()->for($kinesiologa ?? $this->kinesiologa)->create([
            'inicio_at' => $this->fecha('2026-10-05 10:00:00'), 'fin_at' => $this->fecha('2026-10-05 10:30:00'),
        ]);
    }

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-confirmacion'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-confirmacion'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-confirmacion'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'CONF'], ['nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $user = User::factory()->create([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id,
            'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true,
        ]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa->fresh();
    }
}
