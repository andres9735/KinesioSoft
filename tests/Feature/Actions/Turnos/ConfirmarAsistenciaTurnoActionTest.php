<?php

namespace Tests\Feature\Actions\Turnos;

use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Actions\Turnos\ResultadoConfirmacionAsistenciaTurno;
use App\Enums\CanalAutogestionTurnoEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ConfirmacionAsistenciaNoPermitidaException;
use App\Exceptions\Turnos\ProgramacionTurnoModificadaException;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Auditoria;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Kinesiologa;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfirmarAsistenciaTurnoActionTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $ahora;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private Turno $turno;

    private SQLiteGrammar $gramaticaOriginal;

    private LocksConfirmacionSqlite $gramatica;

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
            'motivo_solicitud' => 'Motivo original privado',
        ]);
        $this->gramaticaOriginal = DB::connection()->getQueryGrammar();
        $this->gramatica = new LocksConfirmacionSqlite(DB::connection());
        DB::connection()->setQueryGrammar($this->gramatica);
        Storage::shouldReceive('disk')->never();
    }

    protected function tearDown(): void
    {
        foreach ([Kinesiologa::class, Turno::class, Auditoria::class] as $modelo) {
            Event::forget('eloquent.retrieved: '.$modelo);
            Event::forget('eloquent.creating: '.$modelo);
            Event::forget('eloquent.created: '.$modelo);
            Event::forget('eloquent.updating: '.$modelo);
        }
        DB::connection()->setQueryGrammar($this->gramaticaOriginal);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('tipos')]
    public function test_confirms_both_types_preserving_all_fields_and_occupation(TipoTurnoEnum $tipo): void
    {
        // El cambio de tipo es preparación del fixture, no una operación de dominio.
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo->value]);
        $this->turno->refresh();
        $antes = $this->turno->getAttributes();
        $agenda = app(AgendaQueryService::class);
        $ocupacion = fn () => $agenda->turnosAfectadosPorIntervalo($this->kinesiologa, $this->turno->inicio_at, $this->turno->fin_at)->pluck('id')->all();
        $this->assertSame([$this->turno->id], $ocupacion());
        $nivel = DB::transactionLevel();
        $autorizaciones = 0;
        Gate::after(function (User $actor, string $ability, ?bool $result, array $arguments) use ($nivel, &$autorizaciones): void {
            if ($ability === 'confirmar') {
                $autorizaciones++;
                $this->assertTrue($result);
                $this->assertSame($nivel + 1, DB::transactionLevel());
                $this->assertNotSame($this->turno, $arguments[0]);
                $this->assertTrue($arguments[0]->relationLoaded('paciente'));
                $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], $this->gramatica->locks);
            }
        });
        $resultado = $this->confirmar();
        $this->assertFalse($resultado->esReplay);
        $this->assertSame(1, $autorizaciones);
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $resultado->turno->estado);
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $this->turno->fresh()->estado);
        $this->assertSame(array_diff_key($antes, array_flip(['estado', 'updated_at'])), array_diff_key($this->turno->fresh()->getAttributes(), array_flip(['estado', 'updated_at'])));
        $this->assertSame([$this->turno->id], $ocupacion());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertSame($nivel, DB::transactionLevel());
        $auditoria = Auditoria::sole();
        $this->assertSame('turno_asistencia_confirmada', $auditoria->evento);
        $this->assertSame('Turnos', $auditoria->modulo);
        $this->assertSame($this->turno->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($this->turno->id, (int) $auditoria->auditable_id);
        $this->assertSame($this->paciente->user_id, $auditoria->actor_user_id);
        $contexto = ['turno_id' => $this->turno->id, 'paciente_id' => $this->paciente->id,
            'kinesiologa_id' => $this->kinesiologa->id, 'tipo' => $tipo->value,
            'inicio_at' => $this->turno->inicio_at->toIso8601String(), 'fin_at' => $this->turno->fin_at->toIso8601String()];
        $this->assertSame([...$contexto, 'estado' => 'PENDIENTE'], $auditoria->valores_anteriores);
        $this->assertSame([...$contexto, 'estado' => 'CONFIRMADO', 'actor_user_id' => $this->paciente->user_id,
            'canal' => 'MIS_TURNOS', 'ocurrido_at' => $this->ahora->toIso8601String()], $auditoria->valores_nuevos);
        $this->assertStringNotContainsString('Motivo original privado', json_encode($auditoria->toArray()));
    }

    public static function tipos(): array
    {
        return [[TipoTurnoEnum::PRIMERA_CONSULTA], [TipoTurnoEnum::SEGUIMIENTO]];
    }

    #[DataProvider('anticipaciones')]
    public function test_allows_any_strictly_future_start_without_minimum_notice(int $microsegundos): void
    {
        CarbonImmutable::setTestNow($this->turno->inicio_at->subMicroseconds($microsegundos));
        $this->assertFalse($this->confirmar()->esReplay);
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public static function anticipaciones(): array
    {
        return [[3599000000], [59000000], [1000000], [100000], [1]];
    }

    #[DataProvider('iniciosVencidos')]
    public function test_rejects_first_confirmation_at_or_after_start(int $microsegundos): void
    {
        CarbonImmutable::setTestNow($this->turno->inicio_at->addMicroseconds($microsegundos));
        $this->rechazoSinEfectos(ConfirmacionAsistenciaNoPermitidaException::class, 'inicio_no_futuro');
    }

    public static function iniciosVencidos(): array
    {
        return [[0], [1], [1000000], [3600000000]];
    }

    #[DataProvider('estadosIncompatibles')]
    public function test_current_state_prevents_reactivation_even_when_received_model_is_pending(EstadoTurnoEnum $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado->value]);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->estado);
        $this->rechazoSinEfectos(ConfirmacionAsistenciaNoPermitidaException::class, 'estado_no_permitido');
    }

    public static function estadosIncompatibles(): array
    {
        return [[EstadoTurnoEnum::EN_ATENCION], [EstadoTurnoEnum::ATENDIDO], [EstadoTurnoEnum::AUSENTE], [EstadoTurnoEnum::CANCELADO]];
    }

    public function test_retries_use_current_state_without_duplicate_transition_audit_or_timestamp(): void
    {
        $this->assertFalse($this->confirmar()->esReplay);
        $antes = $this->turno->fresh()->getAttributes();
        $auditoria = Auditoria::sole()->getAttributes();
        Event::listen('eloquent.updating: '.Turno::class, fn () => $this->fail('Una repetición no debe guardar el turno.'));
        CarbonImmutable::setTestNow($this->turno->inicio_at->addDay());
        $this->assertTrue($this->confirmar()->esReplay);
        $this->assertTrue($this->confirmar()->esReplay);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame($auditoria, Auditoria::sole()->getAttributes());
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('turnos', 1);
    }

    public function test_already_confirmed_state_returns_replay_without_an_invented_creation_identity(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CONFIRMADO']);
        $antes = $this->turno->fresh()->getAttributes();
        $this->assertTrue($this->confirmar()->esReplay);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('estadosIncompatibles')]
    public function test_retry_after_later_transition_cannot_restore_confirmation(EstadoTurnoEnum $estado): void
    {
        $this->confirmar();
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado->value]);
        $antes = $this->turno->fresh()->getAttributes();
        try {
            $this->confirmar();
            $this->fail('No debe restaurar Confirmado.');
        } catch (ConfirmacionAsistenciaNoPermitidaException $e) {
            $this->assertSame('estado_no_permitido', $e->motivo);
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 1);
    }

    #[DataProvider('estadosAutorizacion')]
    public function test_foreign_patient_is_rejected_including_replays(EstadoTurnoEnum $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $estado->value]);
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $this->pacienteCompleto()->user);
    }

    public static function estadosAutorizacion(): array
    {
        return [[EstadoTurnoEnum::PENDIENTE], [EstadoTurnoEnum::CONFIRMADO]];
    }

    public function test_missing_patient_profile_cannot_be_forged_by_cached_relations(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleEnum::PACIENTE->value);
        $actor->setRelation('paciente', $this->paciente);
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    #[DataProvider('precondicionesRevocadas')]
    public function test_revalidates_persisted_account_role_and_complete_profile(string $condicion): void
    {
        $actor = $this->paciente->user;
        $this->assertTrue($actor->esPaciente());
        $this->assertTrue($actor->tienePerfilPacienteCompleto());
        match ($condicion) {
            'inactivo' => DB::table('users')->where('id', $actor->id)->update(['activo' => false]),
            'sin_verificar' => DB::table('users')->where('id', $actor->id)->update(['email_verified_at' => null]),
            'datos_personales' => DB::table('users')->where('id', $actor->id)->update(['telefono' => null]),
            'datos_administrativos' => DB::table('pacientes')->where('id', $this->paciente->id)->update(['calle' => '']),
            'sin_declaracion' => DB::table('declaraciones_salud_paciente')->where('paciente_id', $this->paciente->id)->delete(),
            'sin_rol' => $actor->fresh()->removeRole(RoleEnum::PACIENTE->value),
        };
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    public static function precondicionesRevocadas(): array
    {
        return [['inactivo'], ['sin_verificar'], ['datos_personales'], ['datos_administrativos'], ['sin_declaracion'], ['sin_rol']];
    }

    #[DataProvider('rolesAdministrativos')]
    public function test_owning_patient_record_does_not_grant_access_to_other_roles(RoleEnum $rol): void
    {
        $actor = $this->paciente->user;
        $actor->syncRoles([$rol->value]);
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    public static function rolesAdministrativos(): array
    {
        return [[RoleEnum::KINESIOLOGO], [RoleEnum::ADMINISTRADOR], [RoleEnum::SUPERADMIN]];
    }

    public function test_prevalidated_link_context_uses_same_action_without_simulating_an_authenticated_session(): void
    {
        $this->assertGuest();
        $resultado = app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($this->paciente->user, $this->turno, CanalAutogestionTurnoEnum::ENLACE_SEGURO);
        $this->assertFalse($resultado->esReplay);
        $this->assertGuest();
        $this->assertSame('ENLACE_SEGURO', Auditoria::sole()->valores_nuevos['canal']);
        $this->assertTrue($this->confirmar()->esReplay);
        $this->assertDatabaseCount('auditorias', 1);
    }

    #[DataProvider('camposProgramacion')]
    public function test_rejects_stale_programming_before_new_transition_or_replay(string $campo, bool $confirmado): void
    {
        $valor = match ($campo) {
            'inicio_at' => $this->turno->inicio_at->addHour(),
            'fin_at' => $this->turno->fin_at->addHour(),
            'tipo' => TipoTurnoEnum::SEGUIMIENTO->value,
        };
        DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $valor, 'estado' => $confirmado ? 'CONFIRMADO' : 'PENDIENTE']);
        $this->rechazoSinEfectos(ProgramacionTurnoModificadaException::class);
    }

    public static function camposProgramacion(): array
    {
        return [['inicio_at', false], ['fin_at', false], ['tipo', false], ['inicio_at', true], ['fin_at', true], ['tipo', true]];
    }

    public function test_rejects_professional_changed_before_entering_the_service(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['kinesiologa_id' => $this->profesional()->id]);
        $this->rechazoSinEfectos(RecursosTurnoModificadosException::class);
    }

    #[DataProvider('recursos')]
    public function test_service_detects_reassignment_during_protected_read(string $campo): void
    {
        $nuevo = $campo === 'paciente_id' ? $this->pacienteCompleto()->id : $this->profesional()->id;
        // Intercalación secuencial controlada; no demuestra concurrencia MariaDB.
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function () use ($campo, $nuevo): void {
            DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $nuevo]);
        });
        $this->rechazoSinEfectos(RecursosTurnoModificadosException::class);
    }

    public static function recursos(): array
    {
        return [['paciente_id'], ['kinesiologa_id']];
    }

    public function test_time_is_obtained_after_locks_so_expired_pending_turn_is_rejected(): void
    {
        CarbonImmutable::setTestNow($this->turno->inicio_at->subSecond());
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function (): void {
            CarbonImmutable::setTestNow($this->turno->inicio_at);
        });
        $this->rechazoSinEfectos(ConfirmacionAsistenciaNoPermitidaException::class, 'inicio_no_futuro');
    }

    public function test_state_change_during_lock_acquisition_is_not_overwritten(): void
    {
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function (): void {
            DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'EN_ATENCION']);
        });
        $this->rechazoSinEfectos(ConfirmacionAsistenciaNoPermitidaException::class, 'estado_no_permitido');
    }

    public function test_revoked_role_during_lock_acquisition_is_rejected_by_real_policy(): void
    {
        $actor = $this->paciente->user;
        $this->assertTrue($actor->esPaciente());
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function () use ($actor): void {
            $actor->fresh()->removeRole(RoleEnum::PACIENTE->value);
        });
        $this->rechazoSinEfectos(AuthorizationException::class, actor: $actor);
    }

    public function test_time_from_utc_source_is_normalized_to_application_timezone(): void
    {
        $fuenteUtc = CarbonImmutable::parse('2026-10-31 12:59:59.999999', 'UTC');
        // Los timestamps SQL no incluyen zona. El mock conserva la zona de la
        // aplicación, igual que el reloj y la persistencia canónicos del proyecto.
        CarbonImmutable::setTestNow($fuenteUtc->setTimezone(config('app.timezone')));
        $inicio = $this->turno->fresh()->inicio_at;
        $ahora = app(RelojAplicacion::class)->ahora();
        $this->assertTrue($ahora->equalTo($fuenteUtc));
        $this->assertTrue($inicio->greaterThan($ahora));
        $this->assertFalse($this->confirmar()->esReplay);
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $this->turno->fresh()->estado);
    }

    public function test_freshly_consulted_programming_can_be_confirmed_after_stale_request_is_rejected(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'inicio_at' => $this->turno->inicio_at->addDay(), 'fin_at' => $this->turno->fin_at->addDay(),
        ]);
        $this->rechazoSinEfectos(ProgramacionTurnoModificadaException::class);
        $this->turno->refresh();
        $this->assertFalse($this->confirmar()->esReplay);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_caller_changes_to_mutable_model_are_never_saved_or_trusted_as_current_state(): void
    {
        $original = $this->turno->getRawOriginal();
        $this->turno->forceFill(['estado' => 'CANCELADO', 'tipo' => 'SEGUIMIENTO', 'paciente_id' => 999999,
            'inicio_at' => $this->ahora->subDay(), 'motivo_solicitud' => 'No debe guardarse']);
        $resultado = $this->confirmar();
        $this->assertFalse($resultado->esReplay);
        $this->assertSame($original['paciente_id'], $resultado->turno->paciente_id);
        $this->assertSame($original['tipo'], $resultado->turno->tipo->value);
        $this->assertSame($original['motivo_solicitud'], $resultado->turno->motivo_solicitud);
        $this->assertSame($original['inicio_at'], $resultado->turno->getRawOriginal('inicio_at'));
    }

    #[DataProvider('fallosPersistencia')]
    public function test_failure_rolls_back_transition_and_audit_without_retry(string $evento, string $modelo): void
    {
        $error = new RuntimeException('Fallo controlado de persistencia');
        $intentos = 0;
        Event::listen('eloquent.'.$evento.': '.$modelo, function () use ($error, &$intentos): never {
            $intentos++;
            throw $error;
        });
        $antes = $this->turno->fresh()->getAttributes();
        $nivel = DB::transactionLevel();
        try {
            $this->confirmar();
            $this->fail('Debe propagar el error técnico.');
        } catch (RuntimeException $e) {
            $this->assertSame($error, $e);
        }
        $this->assertSame(1, $intentos);
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function fallosPersistencia(): array
    {
        return [['updating', Turno::class], ['creating', Auditoria::class], ['created', Auditoria::class]];
    }

    #[DataProvider('persistenciasCanceladas')]
    public function test_cancelled_persistence_is_a_technical_failure_and_rolls_back(string $evento, string $modelo): void
    {
        Event::listen('eloquent.'.$evento.': '.$modelo, fn () => false);
        $this->rechazoSinEfectos(RuntimeException::class);
    }

    public static function persistenciasCanceladas(): array
    {
        return [['updating', Turno::class], ['creating', Auditoria::class]];
    }

    public function test_lock_failure_is_propagated_without_transition_or_audit(): void
    {
        $this->gramatica->fallo = new QueryException(DB::connection()->getName(), 'select for update', [], new PDOException('Fallo controlado de lock'));
        $this->rechazoSinEfectos(QueryException::class);
    }

    public function test_missing_appointment_cannot_be_confirmed(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->delete();
        $this->expectException(ModelNotFoundException::class);
        try {
            $this->confirmar();
        } finally {
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_unpersisted_appointment_is_rejected_before_locks(): void
    {
        $this->turno = new Turno;
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->confirmar();
        } finally {
            $this->assertSame([], $this->gramatica->locks);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_enclosing_transaction_can_roll_back_both_persistent_effects(): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        try {
            DB::transaction(function (): never {
                $this->confirmar();
                $this->assertDatabaseCount('auditorias', 1);
                throw new RuntimeException('Revertir transacción exterior');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('Revertir transacción exterior', $e->getMessage());
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function confirmar(): ResultadoConfirmacionAsistenciaTurno
    {
        return app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($this->paciente->user, $this->turno);
    }

    private function rechazoSinEfectos(string $excepcion, ?string $motivo = null, ?User $actor = null): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $nivel = DB::transactionLevel();
        try {
            app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($actor ?? $this->paciente->user, $this->turno);
            $this->fail('La operación debe rechazarse.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($excepcion, $e);
            if ($motivo !== null) {
                $this->assertSame($motivo, $e->motivo);
            }
        }
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('turnos', 1);
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
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-confirmacion-asistencia'], ['nombre' => 'Título', 'activo' => true]);

        return Kinesiologa::create(['user_id' => User::factory()->create()->id, 'titulo_profesional_id' => $titulo->id]);
    }
}

/** Sólo observa el contrato de locks; SQLite no certifica carreras ni deadlocks. */
class LocksConfirmacionSqlite extends SQLiteGrammar
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
