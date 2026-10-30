<?php

namespace Tests\Feature\Actions\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Actions\Turnos\ResultadoCancelacionTurno;
use App\Enums\CanalAutogestionTurnoEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Exceptions\Turnos\ConfirmacionAsistenciaNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Auditoria;
use App\Models\DeclaracionSaludPaciente;
use App\Models\DocumentoClinico;
use App\Models\DocumentoClinicoOrigenTurno;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancelarTurnoActionTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $ahora;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private Turno $turno;

    private SQLiteGrammar $gramaticaOriginal;

    private LocksCancelacionSqlite $gramatica;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        $this->ahora = CarbonImmutable::parse('2026-10-30 10:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->ahora);
        $this->paciente = $this->pacienteCompleto();
        $this->kinesiologa = $this->profesional();
        $this->turno = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => EstadoTurnoEnum::PENDIENTE,
            'inicio_at' => $this->ahora->addDay(), 'fin_at' => $this->ahora->addDay()->addMinutes(45),
            'motivo_solicitud' => 'Motivo clínico original privado',
        ])->fresh();
        $this->gramaticaOriginal = DB::connection()->getQueryGrammar();
        $this->gramatica = new LocksCancelacionSqlite(DB::connection());
        DB::connection()->setQueryGrammar($this->gramatica);
        Bus::fake();
        Queue::fake();
        Mail::fake();
        Notification::fake();
        Storage::shouldReceive('disk')->never();
    }

    protected function tearDown(): void
    {
        foreach ([Kinesiologa::class, Turno::class, Auditoria::class, IntencionEvaluacionCupo::class] as $modelo) {
            foreach (['retrieved', 'creating', 'created', 'updating'] as $evento) {
                Event::forget('eloquent.'.$evento.': '.$modelo);
            }
        }
        DB::connection()->setQueryGrammar($this->gramaticaOriginal);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('tiposYEstadosElegibles')]
    public function test_cancels_each_eligible_combination_with_one_audit_and_intention_and_releases_occupation(string $tipo, string $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo, 'estado' => $estado]);
        $this->turno->refresh();
        $antes = $this->turno->getAttributes();
        $this->assertSame([$this->turno->id], $this->ocupacion());
        $nivel = DB::transactionLevel();
        $autorizaciones = 0;
        Gate::after(function (User $actor, string $ability, ?bool $result, array $arguments) use ($nivel, &$autorizaciones): void {
            if ($ability === 'cancelar') {
                $autorizaciones++;
                $this->assertTrue($result);
                $this->assertSame($nivel + 1, DB::transactionLevel());
                $this->assertNotSame($this->turno, $arguments[0]);
                $this->assertTrue($arguments[0]->relationLoaded('paciente'));
                $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], $this->gramatica->locks);
            }
        });
        $escrituras = [];
        DB::listen(function (QueryExecuted $query) use (&$escrituras): void {
            if (preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+["`]?([a-z_]+)/i', $query->sql, $coincidencias)) {
                $escrituras[] = $coincidencias[1];
            }
        });
        $resultado = $this->cancelar();
        $this->assertFalse($resultado->esReplay);
        $this->assertSame(1, $autorizaciones);
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $resultado->turno->estado);
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $this->turno->fresh()->estado);
        $this->assertSame(array_diff_key($antes, array_flip(['estado', 'updated_at'])), array_diff_key($this->turno->fresh()->getAttributes(), array_flip(['estado', 'updated_at'])));
        $this->assertSame([], $this->ocupacion());
        $this->assertSame(['turnos', 'auditorias', 'intenciones_evaluacion_cupo'], $escrituras);
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $auditoria = Auditoria::sole();
        $contexto = ['turno_id' => $this->turno->id, 'paciente_id' => $this->paciente->id,
            'kinesiologa_id' => $this->kinesiologa->id, 'tipo' => $tipo,
            'inicio_at' => $this->turno->inicio_at->toIso8601String(), 'fin_at' => $this->turno->fin_at->toIso8601String()];
        $this->assertSame('turno_cancelado', $auditoria->evento);
        $this->assertSame('Turnos', $auditoria->modulo);
        $this->assertSame($this->turno->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($this->turno->id, (int) $auditoria->auditable_id);
        $this->assertSame($this->paciente->user_id, $auditoria->actor_user_id);
        $this->assertSame([...$contexto, 'estado' => $estado], $auditoria->valores_anteriores);
        $this->assertSame([...$contexto, 'estado' => 'CANCELADO', 'actor_user_id' => $this->paciente->user_id,
            'canal' => 'MIS_TURNOS', 'ocurrido_at' => $this->ahora->toIso8601String()], $auditoria->valores_nuevos);
        $this->assertStringNotContainsString('Motivo clínico original privado', json_encode($auditoria->toArray(), JSON_UNESCAPED_UNICODE));
        $intencion = IntencionEvaluacionCupo::sole();
        $this->assertSame($auditoria->id, $intencion->auditoria_cancelacion_id);
        $this->assertSame($this->turno->id, $intencion->turno_id);
        $this->assertSame($this->kinesiologa->id, $intencion->kinesiologa_id);
        $this->assertTrue($intencion->inicio_at->equalTo($this->turno->inicio_at));
        $this->assertTrue($intencion->fin_at->equalTo($this->turno->fin_at));
        $this->assertTrue($intencion->liberado_at->equalTo($this->ahora));
        $this->assertNull($intencion->procesado_at);
        $this->sinEfectosExternos();
    }

    public static function tiposYEstadosElegibles(): array
    {
        return [['PRIMERA_CONSULTA', 'PENDIENTE'], ['PRIMERA_CONSULTA', 'CONFIRMADO'], ['SEGUIMIENTO', 'PENDIENTE'], ['SEGUIMIENTO', 'CONFIRMADO']];
    }

    #[DataProvider('plazosValidos')]
    public function test_allows_exactly_twelve_hours_or_more_using_complete_instants(int $microsegundos): void
    {
        CarbonImmutable::setTestNow($this->turno->inicio_at->subHours(12)->addMicroseconds($microsegundos));
        $this->assertFalse($this->cancelar()->esReplay);
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public static function plazosValidos(): array
    {
        return [[0], [-1], [-1000000], [-3600000000], [-43200000000]];
    }

    #[DataProvider('plazosInvalidos')]
    public function test_rejects_less_than_twelve_hours_including_start_and_past_without_effects(int $microsegundos): void
    {
        CarbonImmutable::setTestNow($this->turno->inicio_at->subHours(12)->addMicroseconds($microsegundos));
        $this->rechazoSinEfectos(CancelacionTurnoNoPermitidaException::class, 'fuera_de_plazo');
    }

    public static function plazosInvalidos(): array
    {
        return [[1], [1000000], [3600000000], [43200000000], [43200000001], [86400000000]];
    }

    public function test_allows_cancellation_on_same_calendar_day_at_exact_twelve_hour_boundary(): void
    {
        $this->turno->inicio_at = $this->ahora->addHours(12);
        $this->turno->fin_at = $this->ahora->addHours(12)->addMinutes(45);
        $this->turno->save();
        $this->assertSame($this->ahora->toDateString(), $this->turno->inicio_at->toDateString());
        $this->assertFalse($this->cancelar()->esReplay);
    }

    #[DataProvider('estadosInvalidos')]
    public function test_uses_persisted_state_instead_of_stale_pending_model(string $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado]);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->estado);
        $this->rechazoSinEfectos(CancelacionTurnoNoPermitidaException::class, 'estado_no_permitido');
    }

    public static function estadosInvalidos(): array
    {
        return [['EN_ATENCION'], ['ATENDIDO'], ['AUSENTE']];
    }

    public function test_replay_after_deadline_and_start_does_not_write_or_replace_original_reason(): void
    {
        $this->assertFalse($this->cancelar('Motivo administrativo inicial')->esReplay);
        $intencion = IntencionEvaluacionCupo::sole();
        $intencion->procesado_at = $this->ahora->addMinute();
        $intencion->save();
        $antes = [$this->turno->fresh()->getAttributes(), Auditoria::sole()->getAttributes(), $intencion->fresh()->getAttributes()];
        Event::listen('eloquent.updating: '.Turno::class, fn () => $this->fail('Replay no debe guardar el turno.'));
        Event::listen('eloquent.creating: '.Auditoria::class, fn () => $this->fail('Replay no debe auditar otra transición.'));
        Event::listen('eloquent.creating: '.IntencionEvaluacionCupo::class, fn () => $this->fail('Replay no debe generar otra intención.'));
        CarbonImmutable::setTestNow($this->turno->inicio_at->addDay());
        $this->assertTrue($this->cancelar('Motivo posterior')->esReplay);
        $this->assertTrue($this->cancelar(canal: CanalAutogestionTurnoEnum::ENLACE_SEGURO)->esReplay);
        $this->assertSame($antes, [$this->turno->fresh()->getAttributes(), Auditoria::sole()->getAttributes(), IntencionEvaluacionCupo::sole()->getAttributes()]);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->assertSame([], $this->ocupacion());
        $this->sinEfectosExternos();
    }

    public function test_previously_cancelled_turno_reports_replay_without_inventing_missing_historical_effects(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CANCELADO']);
        CarbonImmutable::setTestNow($this->turno->inicio_at->addDay());
        $antes = $this->turno->fresh()->getAttributes();
        $this->assertTrue($this->cancelar()->esReplay);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    #[DataProvider('estadosInvalidos')]
    public function test_retry_does_not_overwrite_later_professional_state(string $estado): void
    {
        $this->cancelar();
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado]);
        $antes = $this->turno->fresh()->getAttributes();
        try {
            $this->cancelar();
            $this->fail('Debe preservar el estado posterior.');
        } catch (CancelacionTurnoNoPermitidaException $e) {
            $this->assertSame('estado_no_permitido', $e->motivo);
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    #[DataProvider('estadosDeAutorizacion')]
    public function test_foreign_patient_is_rejected_even_for_replays(string $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado]);
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $this->pacienteCompleto()->user);
    }

    public static function estadosDeAutorizacion(): array
    {
        return [['PENDIENTE'], ['CONFIRMADO'], ['CANCELADO']];
    }

    #[DataProvider('autorizacionesRevocadas')]
    public function test_revalidates_account_profile_and_roles_without_trusting_cached_user(string $caso): void
    {
        $actor = $this->paciente->user;
        $this->assertTrue($actor->esPaciente());
        $this->assertTrue($actor->tienePerfilPacienteCompleto());
        match ($caso) {
            'inactivo' => DB::table('users')->where('id', $actor->id)->update(['activo' => false]),
            'sin_verificar' => DB::table('users')->where('id', $actor->id)->update(['email_verified_at' => null]),
            'sin_datos_personales' => DB::table('users')->where('id', $actor->id)->update(['telefono' => null]),
            'sin_datos_administrativos' => DB::table('pacientes')->where('id', $this->paciente->id)->update(['calle' => '']),
            'sin_declaracion' => DB::table('declaraciones_salud_paciente')->where('paciente_id', $this->paciente->id)->delete(),
            default => $actor->fresh()->syncRoles($caso === 'sin_rol' ? [] : [$caso]),
        };
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    public static function autorizacionesRevocadas(): array
    {
        return [['inactivo'], ['sin_verificar'], ['sin_datos_personales'], ['sin_datos_administrativos'], ['sin_declaracion'], ['sin_rol'], [RoleEnum::KINESIOLOGO->value], [RoleEnum::ADMINISTRADOR->value], [RoleEnum::SUPERADMIN->value]];
    }

    public function test_actor_without_persisted_patient_cannot_supply_cached_ownership(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleEnum::PACIENTE->value);
        $actor->setRelation('paciente', $this->paciente);
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    public function test_prevalidated_link_context_is_audited_without_faking_authenticated_session(): void
    {
        $this->assertGuest();
        $this->assertFalse($this->cancelar(canal: CanalAutogestionTurnoEnum::ENLACE_SEGURO)->esReplay);
        $this->assertGuest();
        $this->assertSame('ENLACE_SEGURO', Auditoria::sole()->valores_nuevos['canal']);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    #[DataProvider('programacionesObsoletas')]
    public function test_rejects_stale_programming_before_transition_or_replay(string $campo, bool $cancelado): void
    {
        $valor = match ($campo) {
            'inicio_at' => $this->turno->inicio_at->addDay(),
            'fin_at' => $this->turno->fin_at->addHour(),
            'tipo' => 'SEGUIMIENTO',
        };
        DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $valor, 'estado' => $cancelado ? 'CANCELADO' : 'PENDIENTE']);
        $this->rechazoSinEfectos(ProgramacionTurnoModificadaException::class);
    }

    public static function programacionesObsoletas(): array
    {
        return [['inicio_at', false], ['fin_at', false], ['tipo', false], ['inicio_at', true], ['fin_at', true], ['tipo', true]];
    }

    public function test_professional_changed_before_service_entry_is_rejected(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['kinesiologa_id' => $this->profesional()->id]);
        $this->rechazoSinEfectos(RecursosTurnoModificadosException::class);
    }

    #[DataProvider('recursosReasignados')]
    public function test_service_rejects_resource_reassignment_during_lock_acquisition(string $campo): void
    {
        $nuevo = $campo === 'paciente_id' ? $this->pacienteCompleto()->id : $this->profesional()->id;
        // Intercalación secuencial de fixtures; no certifica carreras MariaDB.
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function () use ($campo, $nuevo): void {
            DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $nuevo]);
        });
        $this->rechazoSinEfectos(RecursosTurnoModificadosException::class);
    }

    public static function recursosReasignados(): array
    {
        return [['paciente_id'], ['kinesiologa_id']];
    }

    public function test_deadline_is_rechecked_after_locks_instead_of_using_request_start_time(): void
    {
        $limite = $this->turno->inicio_at->subHours(12);
        CarbonImmutable::setTestNow($limite->subMicrosecond());
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, fn () => CarbonImmutable::setTestNow($limite->addMicrosecond()));
        $this->rechazoSinEfectos(CancelacionTurnoNoPermitidaException::class, 'fuera_de_plazo');
    }

    public function test_state_and_role_are_revalidated_during_protected_read(): void
    {
        $actor = $this->paciente->user;
        $actor->esPaciente();
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function () use ($actor): void {
            $actor->fresh()->removeRole(RoleEnum::PACIENTE->value);
            DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'EN_ATENCION']);
        });
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    public function test_dirty_received_model_cannot_change_owner_programming_or_original_motive(): void
    {
        $antes = $this->turno->getRawOriginal();
        $this->turno->forceFill(['estado' => 'ATENDIDO', 'tipo' => 'SEGUIMIENTO', 'paciente_id' => 999999,
            'inicio_at' => $this->ahora->subDay(), 'motivo_solicitud' => 'No debe persistirse']);
        $this->assertFalse($this->cancelar()->esReplay);
        $actual = $this->turno->fresh();
        $this->assertSame(array_diff_key($antes, array_flip(['estado', 'updated_at'])), array_diff_key($actual->getAttributes(), array_flip(['estado', 'updated_at'])));
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $actual->estado);
    }

    #[DataProvider('motivosOpcionales')]
    public function test_optional_cancellation_reason_is_normalized_only_in_the_audit(?string $motivo, ?string $esperado): void
    {
        $original = $this->turno->motivo_solicitud;
        $this->cancelar($motivo);
        $nuevos = Auditoria::sole()->valores_nuevos;
        if ($esperado === null) {
            $this->assertArrayNotHasKey('motivo_cancelacion', $nuevos);
        } else {
            $this->assertSame($esperado, $nuevos['motivo_cancelacion']);
        }
        $this->assertSame($original, $this->turno->fresh()->motivo_solicitud);
        $this->assertArrayNotHasKey('motivo_cancelacion', IntencionEvaluacionCupo::sole()->getAttributes());
    }

    public static function motivosOpcionales(): array
    {
        return [[null, null], ['', null], [" \n\t ", null], ['  Compromiso personal  ', 'Compromiso personal']];
    }

    public function test_cancellation_preserves_original_documents_without_opening_storage(): void
    {
        $documento = DocumentoClinico::factory()->for($this->paciente)->create(['cargado_por_user_id' => $this->paciente->user_id, 'descripcion' => 'Descripción original']);
        $asociacion = DocumentoClinicoOrigenTurno::create(['documento_clinico_id' => $documento->id, 'turno_id' => $this->turno->id]);
        $antes = [$documento->fresh()->getAttributes(), $asociacion->fresh()->getAttributes()];
        $this->cancelar();
        $this->assertSame($antes, [$documento->fresh()->getAttributes(), $asociacion->fresh()->getAttributes()]);
        $this->assertDatabaseCount('documentos_clinicos', 1);
        $this->assertDatabaseCount('documentos_clinicos_origen_turno', 1);
        $this->sinEfectosExternos();
    }

    public function test_followup_cancellation_does_not_touch_other_scheduled_turnos_or_create_replacements(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => 'SEGUIMIENTO']);
        $this->turno->refresh();
        $otro = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create(['inicio_at' => $this->ahora->addDays(2), 'fin_at' => $this->ahora->addDays(2)->addMinutes(45)])->fresh();
        $antes = $otro->getAttributes();
        $this->cancelar();
        $this->assertSame($antes, $otro->fresh()->getAttributes());
        $this->assertDatabaseCount('turnos', 2);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->sinEfectosExternos();
    }

    public function test_releasing_own_occupation_does_not_remove_another_occupying_turno(): void
    {
        $otro = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'inicio_at' => $this->turno->inicio_at, 'fin_at' => $this->turno->fin_at, 'estado' => 'CONFIRMADO',
        ])->fresh();
        $this->assertSame([$this->turno->id, $otro->id], $this->ocupacion());
        $this->cancelar();
        $this->assertSame([$otro->id], $this->ocupacion());
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $otro->fresh()->estado);
    }

    public function test_distinct_cancellations_for_same_interval_keep_distinct_audits_and_intentions(): void
    {
        $this->cancelar();
        $primera = IntencionEvaluacionCupo::sole();
        $this->turno = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'inicio_at' => $this->turno->inicio_at, 'fin_at' => $this->turno->fin_at,
        ])->fresh();
        $this->assertFalse($this->cancelar()->esReplay);
        $segunda = IntencionEvaluacionCupo::query()->where('id', '!=', $primera->id)->sole();
        $this->assertNotSame($primera->auditoria_cancelacion_id, $segunda->auditoria_cancelacion_id);
        $this->assertTrue($primera->inicio_at->equalTo($segunda->inicio_at));
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 2);
        $this->assertDatabaseCount('auditorias', 2);
    }

    public function test_confirmation_then_cancellation_are_distinct_valid_operations(): void
    {
        app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($this->paciente->user, $this->turno);
        $this->assertFalse($this->cancelar()->esReplay);
        $this->assertSame(['turno_asistencia_confirmada', 'turno_cancelado'], Auditoria::query()->orderBy('id')->pluck('evento')->all());
        $this->assertSame('CONFIRMADO', Auditoria::query()->latest('id')->first()->valores_anteriores['estado']);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public function test_confirmation_after_cancellation_cannot_reactivate_the_turno(): void
    {
        $this->cancelar();
        try {
            app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($this->paciente->user, $this->turno);
            $this->fail('No debe reactivar el turno cancelado.');
        } catch (ConfirmacionAsistenciaNoPermitidaException $e) {
            $this->assertSame('estado_no_permitido', $e->motivo);
        }
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    #[DataProvider('fallosPersistentes')]
    public function test_failure_in_turno_audit_or_intention_rolls_back_all_effects_without_retry(string $evento, string $modelo, bool $cancelar): void
    {
        $error = new RuntimeException('Fallo controlado de persistencia');
        $intentos = 0;
        Event::listen('eloquent.'.$evento.': '.$modelo, function () use ($cancelar, $error, &$intentos) {
            $intentos++;
            if ($cancelar) {
                return false;
            }
            throw $error;
        });
        $antes = $this->turno->fresh()->getAttributes();
        $nivel = DB::transactionLevel();
        try {
            $this->cancelar();
            $this->fail('Debe propagar el error técnico.');
        } catch (RuntimeException $e) {
            if (! $cancelar) {
                $this->assertSame($error, $e);
            }
        }
        $this->assertSame(1, $intentos);
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame([$this->turno->id], $this->ocupacion());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->sinEfectosExternos();
    }

    public static function fallosPersistentes(): array
    {
        return [['updating', Turno::class, false], ['updating', Turno::class, true],
            ['creating', Auditoria::class, false], ['created', Auditoria::class, false], ['creating', Auditoria::class, true],
            ['creating', IntencionEvaluacionCupo::class, false], ['created', IntencionEvaluacionCupo::class, false], ['creating', IntencionEvaluacionCupo::class, true]];
    }

    public function test_enclosing_transaction_can_rollback_cancel_audit_and_intention_together(): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $error = new RuntimeException('Revertir transacción exterior');
        try {
            DB::transaction(function () use ($error): never {
                $this->cancelar();
                $this->assertDatabaseCount('auditorias', 1);
                $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
                throw $error;
            });
            $this->fail('Debe propagar el fallo exterior.');
        } catch (RuntimeException $e) {
            $this->assertSame($error, $e);
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_lock_failure_is_propagated_without_a_partial_operation(): void
    {
        $this->gramatica->fallo = new QueryException(DB::connection()->getName(), 'select for update', [], new PDOException('Fallo controlado de lock'));
        $this->rechazoSinEfectos(QueryException::class);
    }

    public function test_missing_turno_is_rejected_without_audit_or_intention(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->delete();
        $this->expectException(ModelNotFoundException::class);
        try {
            $this->cancelar();
        } finally {
            $this->assertDatabaseCount('auditorias', 0);
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public function test_unpersisted_turno_is_rejected_before_locks(): void
    {
        $this->turno = new Turno;
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->cancelar();
        } finally {
            $this->assertSame([], $this->gramatica->locks);
        }
    }

    private function cancelar(?string $motivo = null, ?User $actor = null, CanalAutogestionTurnoEnum $canal = CanalAutogestionTurnoEnum::MIS_TURNOS): ResultadoCancelacionTurno
    {
        return app(CancelarTurnoAction::class)->ejecutar($actor ?? $this->paciente->user, $this->turno, $motivo, $canal);
    }

    private function ocupacion(): array
    {
        return app(AgendaQueryService::class)->turnosAfectadosPorIntervalo($this->kinesiologa, $this->turno->inicio_at, $this->turno->fin_at)->pluck('id')->all();
    }

    private function rechazoSinEfectos(string $excepcion, ?string $motivo = null, ?User $actor = null): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $nivel = DB::transactionLevel();
        try {
            $this->cancelar(actor: $actor);
            $this->fail('Debe rechazar la operación.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($excepcion, $e);
            if ($motivo !== null) {
                $this->assertSame($motivo, $e->motivo);
                if ($motivo === 'fuera_de_plazo') {
                    $this->assertStringContainsString('consultorio', $e->getMessage());
                }
            }
        }
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->sinEfectosExternos();
    }

    private function sinEfectosExternos(): void
    {
        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    private function pacienteCompleto(): Paciente
    {
        $user = User::factory()->create(['dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01', 'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true]);
        $user->assignRole(RoleEnum::PACIENTE->value);
        $provincia = Provincia::firstOrCreate(['georef_id' => '54'], ['nombre' => 'Misiones']);
        $localidad = Localidad::firstOrCreate(['georef_id' => '54098010'], ['provincia_id' => $provincia->id, 'nombre' => 'Posadas', 'departamento_nombre' => 'Capital']);
        $paciente = Paciente::create(['user_id' => $user->id, 'activo' => true, 'localidad_id' => $localidad->id,
            'calle' => 'Mitre', 'altura_domicilio' => '123', 'contacto_emergencia_nombre' => 'Contacto', 'contacto_emergencia_telefono' => '3764123457']);
        DeclaracionSaludPaciente::create(['paciente_id' => $paciente->id, 'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 70, 'altura_cm' => 170, 'ocupacion' => 'Docente', 'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas', 'estado' => DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION]);

        return $paciente;
    }

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-cancelacion-turno'], ['nombre' => 'Título', 'activo' => true]);

        return Kinesiologa::create(['user_id' => User::factory()->create()->id, 'titulo_profesional_id' => $titulo->id]);
    }
}

/** Sólo observa solicitudes de locks; SQLite no certifica concurrencia MariaDB. */
class LocksCancelacionSqlite extends SQLiteGrammar
{
    public array $locks = [];

    public ?QueryException $fallo = null;

    protected function compileLock(Builder $query, $value)
    {
        if ($value === true) {
            $this->locks[] = $query->from;
            if ($this->fallo !== null) {
                throw $this->fallo;
            }
        }

        return parent::compileLock($query, $value);
    }
}
