<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\RecursosTurnoModificadosException;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Turnos\TransaccionTurnoService;
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
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransaccionTurnoServiceTest extends TestCase
{
    use RefreshDatabase;

    private Turno $turno;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private CarbonImmutable $ahora;

    private SQLiteGrammar $gramaticaOriginal;

    private RegistroLocksTurnoSqlite $gramatica;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
        $this->ahora = CarbonImmutable::parse('2026-10-30 10:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->ahora);
        $this->paciente = $this->crearPaciente();
        $this->kinesiologa = $this->crearKinesiologa();
        $this->turno = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'inicio_at' => $this->ahora->addDay(), 'fin_at' => $this->ahora->addDay()->addMinutes(45),
        ]);
        $this->gramaticaOriginal = DB::connection()->getQueryGrammar();
        $this->gramatica = new RegistroLocksTurnoSqlite(DB::connection());
        DB::connection()->setQueryGrammar($this->gramatica);
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.retrieved: '.Kinesiologa::class);
        CarbonImmutable::setTestNow();
        DB::connection()->setQueryGrammar($this->gramaticaOriginal);
        parent::tearDown();
    }

    #[DataProvider('tiposYEstados')]
    public function test_locks_persisted_resources_in_canonical_order_without_functional_effects(TipoTurnoEnum $tipo, EstadoTurnoEnum $estado): void
    {
        $this->turno->forceFill(['tipo' => $tipo, 'estado' => $estado])->save();
        $antes = $this->turno->fresh()->getAttributes();
        $nivel = DB::transactionLevel();
        $resultado = app(TransaccionTurnoService::class)->ejecutar($this->turno->id, function (Turno $actual, CarbonImmutable $ahora) use ($antes, $nivel): string {
            $this->assertSame($nivel + 1, DB::transactionLevel());
            $this->assertNotSame($this->turno, $actual);
            $this->assertSame($antes, $actual->getAttributes());
            $this->assertTrue($actual->relationLoaded('paciente'));
            $this->assertTrue($actual->relationLoaded('kinesiologa'));
            $this->assertSame($this->paciente->id, $actual->paciente->id);
            $this->assertSame($this->kinesiologa->id, $actual->kinesiologa->id);
            $this->assertTrue($ahora->equalTo($this->ahora));
            $this->assertSame(config('app.timezone'), $ahora->timezoneName);
            $this->assertTrue(Gate::forUser($this->paciente->user)->authorize('confirmar', $actual)->allowed());

            return 'contexto validado';
        });
        $this->assertSame('contexto validado', $resultado);
        $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], $this->gramatica->locks);
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function tiposYEstados(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                $casos[$tipo->value.' '.$estado->value] = [$tipo, $estado];
            }
        }

        return $casos;
    }

    public function test_callback_receives_current_fields_and_time_after_locks_instead_of_a_stale_model(): void
    {
        $nuevoInicio = $this->ahora->addDays(2);
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'estado' => EstadoTurnoEnum::EN_ATENCION->value,
            'inicio_at' => $nuevoInicio, 'fin_at' => $nuevoInicio->addMinutes(30),
        ]);
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function (): void {
            CarbonImmutable::setTestNow($this->ahora->addMinute());
        });
        app(TransaccionTurnoService::class)->ejecutar($this->turno->id, function (Turno $actual, CarbonImmutable $ahora) use ($nuevoInicio): void {
            $this->assertSame(EstadoTurnoEnum::EN_ATENCION, $actual->estado);
            $this->assertTrue($actual->inicio_at->equalTo($nuevoInicio));
            $this->assertTrue($actual->fin_at->equalTo($nuevoInicio->addMinutes(30)));
            $this->assertTrue($ahora->equalTo($this->ahora->addMinute()));
        });
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->estado);
    }

    public function test_authorization_is_revalidated_by_the_consumer_inside_the_transaction(): void
    {
        $ajeno = $this->crearPaciente()->user;
        $nivel = DB::transactionLevel();
        try {
            app(TransaccionTurnoService::class)->ejecutar($this->turno->id, function (Turno $actual) use ($ajeno, $nivel): void {
                $this->assertSame($nivel + 1, DB::transactionLevel());
                Gate::forUser($ajeno)->authorize('cancelar', $actual);
                $this->fail('No debe superar la autorización del recurso.');
            });
            $this->fail('Debe propagarse el rechazo de autorización.');
        } catch (AuthorizationException) {
            $this->assertSame($nivel, DB::transactionLevel());
        }
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_callback_database_writes_are_atomic_and_exceptions_are_not_retried_or_translated(): void
    {
        $nombre = $this->paciente->user->name;
        $calle = $this->paciente->calle;
        $fallo = new RuntimeException('Fallo de prueba antes de consolidar');
        $intentos = 0;
        $nivel = DB::transactionLevel();
        try {
            app(TransaccionTurnoService::class)->ejecutar($this->turno->id, function (Turno $actual) use ($fallo, &$intentos): never {
                $intentos++;
                DB::table('users')->where('id', $actual->paciente->user_id)->update(['name' => 'Cambio de prueba']);
                DB::table('pacientes')->where('id', $actual->paciente_id)->update(['calle' => 'Calle de prueba']);
                throw $fallo;
            });
            $this->fail('Debe propagarse el fallo.');
        } catch (RuntimeException $error) {
            $this->assertSame($fallo, $error);
        }
        $this->assertSame(1, $intentos);
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame($nombre, $this->paciente->user()->value('name'));
        $this->assertSame($calle, $this->paciente->fresh()->calle);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_successful_callback_and_ambient_transaction_keep_their_atomic_boundaries(): void
    {
        $nombre = $this->paciente->user->name;
        $nivel = DB::transactionLevel();
        $fallo = new RuntimeException('Revertir también la transacción exterior');
        try {
            DB::transaction(function () use ($fallo): void {
                $resultado = app(TransaccionTurnoService::class)->ejecutar($this->turno->id, function (Turno $actual): int {
                    return DB::table('users')->where('id', $actual->paciente->user_id)->update(['name' => 'Cambio transaccional de prueba']);
                });
                $this->assertSame(1, $resultado);
                $this->assertSame('Cambio transaccional de prueba', $this->paciente->user()->value('name'));
                throw $fallo;
            });
            $this->fail('Debe propagarse la falla exterior.');
        } catch (RuntimeException $error) {
            $this->assertSame($fallo, $error);
            $this->assertSame($nivel, DB::transactionLevel());
        }
        $this->assertSame($nombre, $this->paciente->user()->value('name'));
    }

    #[DataProvider('recursosReasignados')]
    public function test_reassignment_invalidates_the_acquired_mutex_before_callback(string $campo): void
    {
        $nuevoId = $campo === 'kinesiologa_id' ? $this->crearKinesiologa()->id : $this->crearPaciente()->id;
        // Intercalación secuencial controlada: prueba el contrato, no una carrera MariaDB.
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function () use ($campo, $nuevoId): void {
            DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $nuevoId]);
        });
        try {
            app(TransaccionTurnoService::class)->ejecutar($this->turno->id, fn () => $this->fail('No debe operar con recursos reasignados.'));
            $this->fail('Debe rechazar la reasignación.');
        } catch (RecursosTurnoModificadosException) {
            $this->assertSame($this->turno->getAttribute($campo), $this->turno->fresh()->getAttribute($campo));
        }
        $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], $this->gramatica->locks);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function recursosReasignados(): array
    {
        return [['kinesiologa_id'], ['paciente_id']];
    }

    public function test_missing_appointment_is_rejected_without_callback_or_locks(): void
    {
        $this->expectException(ModelNotFoundException::class);
        try {
            app(TransaccionTurnoService::class)->ejecutar(999999, fn () => $this->fail('El recurso no existe.'));
        } finally {
            $this->assertSame([], $this->gramatica->locks);
        }
    }

    public function test_appointment_removed_after_reference_is_rejected_by_the_locked_read(): void
    {
        // Cambio secuencial de fixtures; no certifica concurrencia entre conexiones.
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function (): void {
            DB::table('turnos')->where('id', $this->turno->id)->delete();
        });
        $nivel = DB::transactionLevel();
        try {
            app(TransaccionTurnoService::class)->ejecutar($this->turno->id, fn () => $this->fail('El turno ya no existe.'));
            $this->fail('Debe propagarse la falta del recurso.');
        } catch (ModelNotFoundException) {
            $this->assertSame($nivel, DB::transactionLevel());
        }
        $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], $this->gramatica->locks);
        $this->assertDatabaseHas('turnos', ['id' => $this->turno->id, 'estado' => EstadoTurnoEnum::PENDIENTE->value]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('identificadoresInvalidos')]
    public function test_invalid_ids_are_rejected_without_callback(int $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TransaccionTurnoService::class)->ejecutar($id, fn () => $this->fail('El identificador es inválido.'));
    }

    public static function identificadoresInvalidos(): array
    {
        return [[0], [-1]];
    }

    public function test_failure_to_acquire_a_lock_is_propagated_without_running_the_callback(): void
    {
        $fallo = new QueryException(DB::connection()->getName(), 'select recurso for update', [], new PDOException('Fallo controlado del driver'));
        $this->gramatica->fallo = $fallo;
        $nivel = DB::transactionLevel();
        try {
            app(TransaccionTurnoService::class)->ejecutar($this->turno->id, fn () => $this->fail('El bloqueo falló.'));
            $this->fail('Debe propagarse el error del driver.');
        } catch (QueryException $error) {
            $this->assertSame($fallo, $error);
        }
        $this->assertSame($nivel, DB::transactionLevel());
        $this->assertSame(['kinesiologas'], $this->gramatica->locks);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function crearPaciente(): Paciente
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::PACIENTE->value);

        return Paciente::create(['user_id' => $user->id, 'activo' => true]);
    }

    private function crearKinesiologa(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-transaccion-turno'], ['nombre' => 'Título', 'activo' => true]);

        return Kinesiologa::create(['user_id' => User::factory()->create()->id, 'titulo_profesional_id' => $titulo->id]);
    }
}

/** Registra solicitudes lockForUpdate; SQLite no ofrece bloqueo de filas MariaDB. */
class RegistroLocksTurnoSqlite extends SQLiteGrammar
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
