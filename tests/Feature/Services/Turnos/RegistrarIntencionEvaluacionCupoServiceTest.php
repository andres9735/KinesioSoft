<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\CanalAutogestionTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Turnos\RegistrarIntencionEvaluacionCupoService;
use App\Services\Turnos\TransaccionTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RegistrarIntencionEvaluacionCupoServiceTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $ahora;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahora = CarbonImmutable::parse('2026-10-30 10:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->ahora);
        $paciente = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-intencion-cupo'], ['nombre' => 'Título', 'activo' => true]);
        $profesional = Kinesiologa::create(['user_id' => User::factory()->create()->id, 'titulo_profesional_id' => $titulo->id]);
        // Estado del fixture: la infraestructura nunca realiza esta transición.
        $this->turno = Turno::factory()->for($paciente)->for($profesional)->create([
            'estado' => 'CANCELADO', 'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'inicio_at' => $this->ahora->addHours(12), 'fin_at' => $this->ahora->addHours(12)->addMinutes(45),
            'motivo_solicitud' => 'Dato clínico sintético que no debe copiarse',
        ])->fresh();
        Bus::fake();
        Queue::fake();
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        foreach (['creating', 'created'] as $evento) {
            Event::forget('eloquent.'.$evento.': '.IntencionEvaluacionCupo::class);
        }
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('cancelacionesElegibles')]
    public function test_persists_minimal_context_for_each_eligible_type_state_and_channel(string $tipo, string $estado, string $canal): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo]);
        $this->turno->refresh();
        $cancelacion = $this->cancelacion(estadoAnterior: $estado, canal: $canal);
        $antes = $this->turno->fresh()->getAttributes();
        $nivel = DB::transactionLevel();
        $intencion = $this->registrar($cancelacion);
        $this->assertTrue($intencion->exists);
        $this->assertSame($nivel, DB::transactionLevel());
        $persistida = IntencionEvaluacionCupo::query()->sole();
        $this->assertNotSame($intencion, $persistida);
        $this->assertSame($cancelacion->id, $persistida->auditoria_cancelacion_id);
        $this->assertSame($this->turno->id, $persistida->turno_id);
        $this->assertSame($this->turno->kinesiologa_id, $persistida->kinesiologa_id);
        $this->assertTrue($this->turno->inicio_at->equalTo($persistida->inicio_at));
        $this->assertTrue($this->turno->fin_at->equalTo($persistida->fin_at));
        $this->assertTrue($this->ahora->equalTo($persistida->liberado_at));
        $this->assertNull($persistida->procesado_at);
        $this->assertNotNull($persistida->created_at);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertSame($cancelacion->id, $persistida->cancelacion->id);
        $this->assertSame($this->turno->id, $persistida->turno->id);
        $this->assertSame($this->turno->kinesiologa_id, $persistida->kinesiologa->id);
        $this->assertSame(['id', 'auditoria_cancelacion_id', 'turno_id', 'kinesiologa_id', 'inicio_at', 'fin_at', 'liberado_at', 'procesado_at', 'created_at'], array_keys($persistida->getAttributes()));
        $this->assertStringNotContainsString('Dato clínico', json_encode($persistida->getAttributes(), JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->sinEfectosExternos();
    }

    public static function cancelacionesElegibles(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (['PENDIENTE', 'CONFIRMADO'] as $estado) {
                foreach (CanalAutogestionTurnoEnum::cases() as $canal) {
                    $casos[] = [$tipo->value, $estado, $canal->value];
                }
            }
        }

        return $casos;
    }

    public function test_repeated_registration_returns_the_same_intention_without_writes(): void
    {
        $cancelacion = $this->cancelacion();
        $primera = $this->registrar($cancelacion)->fresh();
        $antes = $primera->getAttributes();
        Event::listen('eloquent.creating: '.IntencionEvaluacionCupo::class, fn () => $this->fail('No debe insertar otra intención.'));
        CarbonImmutable::setTestNow($this->ahora->addDays(3));
        $repetida = $this->registrar($cancelacion);
        $this->assertSame($primera->id, $repetida->id);
        $this->assertSame($antes, $repetida->getAttributes());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->sinEfectosExternos();
    }

    public function test_retry_does_not_reset_processed_status_or_depend_on_later_turno_changes(): void
    {
        $cancelacion = $this->cancelacion();
        $primera = $this->registrar($cancelacion);
        // Sólo simula la marca persistente del futuro consumidor; no lo ejecuta.
        $primera->procesado_at = $this->ahora->addMinute();
        $primera->save();
        $antes = $primera->fresh()->getAttributes();
        DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => $this->ahora->addDays(2)]);
        $this->assertSame($antes, $this->registrar($cancelacion)->getAttributes());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->sinEfectosExternos();
    }

    public function test_distinct_cancellations_at_same_professional_and_interval_are_not_deduplicated(): void
    {
        $primera = $this->registrar($this->cancelacion());
        $otro = $this->turno->replicate();
        $otro->save();
        $segunda = $this->registrar($this->cancelacion(turno: $otro->fresh()));
        $this->assertNotSame($primera->id, $segunda->id);
        $this->assertNotSame($primera->auditoria_cancelacion_id, $segunda->auditoria_cancelacion_id);
        $this->assertNotSame($primera->turno_id, $segunda->turno_id);
        $this->assertSame($primera->kinesiologa_id, $segunda->kinesiologa_id);
        $this->assertTrue($primera->inicio_at->equalTo($segunda->inicio_at));
        $this->assertTrue($primera->fin_at->equalTo($segunda->fin_at));
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 2);
    }

    public function test_database_unique_constraint_prevents_duplicates_even_outside_the_service(): void
    {
        $primera = $this->registrar($this->cancelacion())->fresh()->getAttributes();
        unset($primera['id']);
        $this->expectException(UniqueConstraintViolationException::class);
        try {
            DB::table('intenciones_evaluacion_cupo')->insert($primera);
        } finally {
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        }
    }

    public function test_existing_intention_with_inconsistent_context_is_not_silently_accepted(): void
    {
        $cancelacion = $this->cancelacion();
        $primera = $this->registrar($cancelacion);
        DB::table('intenciones_evaluacion_cupo')->where('id', $primera->id)->update(['fin_at' => $this->turno->fin_at->addMinute()]);
        $this->expectException(LogicException::class);
        $this->registrar($cancelacion);
    }

    public function test_requires_an_active_transaction_in_the_same_connection(): void
    {
        $cancelacion = $this->cancelacion();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::rollBack();
        $this->assertSame(0, DB::transactionLevel());
        $this->expectException(LogicException::class);
        $this->registrar($cancelacion);
    }

    public function test_rejects_a_foreign_connection_without_connecting_to_it(): void
    {
        $cancelacion = $this->cancelacion();
        $cancelacion->setConnection('conexion_no_autorizada');
        $this->rechazoSinIntencion($cancelacion);
    }

    public function test_rejects_unpersisted_event(): void
    {
        $this->rechazoSinIntencion(new Auditoria);
    }

    public function test_does_not_trust_in_memory_changes_to_the_persisted_audit(): void
    {
        $cancelacion = $this->cancelacion();
        $cancelacion->evento = 'turno_asistencia_confirmada';
        $cancelacion->valores_nuevos = ['estado' => 'PENDIENTE'];
        $this->assertTrue($this->registrar($cancelacion)->exists);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    #[DataProvider('datosInvalidos')]
    public function test_rejects_incomplete_or_inconsistent_cancellation_evidence(string $caso): void
    {
        $nuevos = [];
        $anteriores = [];
        $evento = 'turno_cancelado';
        switch ($caso) {
            case 'sin_hora': $nuevos['ocurrido_at'] = null;
                break;
            case 'hora_relativa': $nuevos['ocurrido_at'] = 'tomorrow';
                break;
            case 'fecha_invalida': $nuevos['ocurrido_at'] = '2026-02-30T10:00:00-03:00';
                break;
            case 'sin_profesional': $nuevos['kinesiologa_id'] = null;
                break;
            case 'otro_turno': $nuevos['turno_id'] = 999999;
                break;
            case 'tipo_invalido': $nuevos['tipo'] = 'SOBRETURNO';
                $anteriores['tipo'] = 'SOBRETURNO';
                break;
            case 'estado_origen': $anteriores['estado'] = 'ATENDIDO';
                break;
            case 'sin_cancelar': $nuevos['estado'] = 'CONFIRMADO';
                break;
            case 'canal_profesional': $nuevos['canal'] = 'PROFESIONAL';
                break;
            case 'sin_actor': $nuevos['actor_user_id'] = null;
                break;
            case 'otro_actor': $nuevos['actor_user_id'] = 999999;
                break;
            case 'otro_evento': $evento = 'turno_asistencia_confirmada';
                break;
            case 'programacion_alterada': $nuevos['inicio_at'] = $this->turno->inicio_at->addHour()->toIso8601String();
                break;
            case 'intervalo_vacio': $nuevos['fin_at'] = $this->turno->inicio_at->toIso8601String();
                $anteriores['fin_at'] = $nuevos['fin_at'];
                break;
            case 'menos_de_12_horas': $nuevos['ocurrido_at'] = $this->ahora->addSecond()->toIso8601String();
                break;
        }
        $this->rechazoSinIntencion($this->cancelacion(nuevos: $nuevos, anteriores: $anteriores, evento: $evento));
    }

    public static function datosInvalidos(): array
    {
        return array_map(fn ($caso) => [$caso], ['sin_hora', 'hora_relativa', 'fecha_invalida', 'sin_profesional', 'otro_turno', 'tipo_invalido', 'estado_origen', 'sin_cancelar', 'canal_profesional', 'sin_actor', 'otro_actor', 'otro_evento', 'programacion_alterada', 'intervalo_vacio', 'menos_de_12_horas']);
    }

    public function test_rejects_evidence_that_does_not_match_the_persisted_turno(): void
    {
        $cancelacion = $this->cancelacion();
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'PENDIENTE']);
        $this->rechazoSinIntencion($cancelacion);
    }

    public function test_mismatched_persisted_programming_is_not_replaced_by_the_audit_snapshot(): void
    {
        $cancelacion = $this->cancelacion();
        DB::table('turnos')->where('id', $this->turno->id)->update(['fin_at' => $this->turno->fin_at->addHour()]);
        $this->rechazoSinIntencion($cancelacion);
    }

    public function test_utc_event_time_preserves_the_exact_twelve_hour_boundary(): void
    {
        $cancelacion = $this->cancelacion(nuevos: ['ocurrido_at' => $this->ahora->utc()->toIso8601String()]);
        $intencion = $this->registrar($cancelacion)->fresh();
        $this->assertTrue($intencion->liberado_at->equalTo($this->ahora));
        $this->assertTrue($intencion->inicio_at->equalTo($this->ahora->addHours(12)));
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public function test_missing_persisted_event_is_not_replaced_by_an_intention(): void
    {
        $cancelacion = new Auditoria;
        $cancelacion->setRawAttributes(['id' => 999999], true);
        $cancelacion->exists = true;
        $this->expectException(ModelNotFoundException::class);
        try {
            $this->registrar($cancelacion);
        } finally {
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public function test_rollback_removes_intention_and_audit_in_shared_transaction(): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        $error = new RuntimeException('Fallo posterior al registro');
        try {
            app(TransaccionTurnoService::class)->ejecutar($this->turno->id, function (Turno $actual) use ($error): never {
                $this->registrar($this->cancelacion(turno: $actual));
                $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
                $this->assertDatabaseCount('auditorias', 1);
                throw $error;
            });
            $this->fail('Debe propagar el fallo.');
        } catch (RuntimeException $e) {
            $this->assertSame($error, $e);
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->assertDatabaseCount('auditorias', 0);
        $this->sinEfectosExternos();
    }

    #[DataProvider('fallosDeRegistro')]
    public function test_registration_failure_leaves_no_partial_persistent_effects(string $evento, bool $cancelar): void
    {
        $intentos = 0;
        Event::listen('eloquent.'.$evento.': '.IntencionEvaluacionCupo::class, function () use ($cancelar, &$intentos) {
            $intentos++;
            if ($cancelar) {
                return false;
            }
            throw new RuntimeException('Fallo de registro controlado');
        });
        try {
            DB::transaction(fn () => $this->registrar($this->cancelacion()));
            $this->fail('Debe fallar el registro.');
        } catch (RuntimeException) {
            $this->assertSame(1, $intentos);
        }
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->assertDatabaseCount('auditorias', 0);
        $this->sinEfectosExternos();
    }

    public static function fallosDeRegistro(): array
    {
        return [['creating', false], ['created', false], ['creating', true]];
    }

    public function test_schema_has_persistent_uniqueness_restrictive_relations_and_pending_marker(): void
    {
        $this->assertTrue(Schema::hasTable('intenciones_evaluacion_cupo'));
        $claves = collect(DB::select("PRAGMA foreign_key_list('intenciones_evaluacion_cupo')"));
        $this->assertSame(3, $claves->count());
        foreach (['auditoria_cancelacion_id' => 'auditorias', 'turno_id' => 'turnos', 'kinesiologa_id' => 'kinesiologas'] as $campo => $tabla) {
            $fk = $claves->firstWhere('from', $campo);
            $this->assertSame($tabla, $fk->table);
            $this->assertSame('RESTRICT', $fk->on_delete);
            $this->assertSame('RESTRICT', $fk->on_update);
        }
        $columnas = collect(DB::select("PRAGMA table_info('intenciones_evaluacion_cupo')"));
        $this->assertSame(0, $columnas->firstWhere('name', 'procesado_at')->notnull);
        foreach (['auditoria_cancelacion_id', 'turno_id', 'kinesiologa_id', 'inicio_at', 'fin_at', 'liberado_at', 'created_at'] as $campo) {
            $this->assertSame(1, $columnas->firstWhere('name', $campo)->notnull);
        }
        $unicos = collect(DB::select("PRAGMA index_list('intenciones_evaluacion_cupo')"))->where('unique', 1);
        $this->assertCount(1, $unicos);
        $indice = $unicos->sole()->name;
        $this->assertSame(['auditoria_cancelacion_id'], collect(DB::select('PRAGMA index_info("'.$indice.'")'))->pluck('name')->all());
    }

    public function test_migration_compiles_for_mariadb_104_without_a_server_connection(): void
    {
        $conexion = new MariaDbConnection(fn () => throw new LogicException('No se permite conectar con MariaDB en esta prueba.'), '', '', [
            'driver' => 'mariadb', 'version' => '10.4.32', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        ]);
        $schema = Schema::getFacadeRoot();
        try {
            Schema::swap($conexion->getSchemaBuilder());
            // Compilación y log de SQL únicamente; pretend impide su ejecución.
            $consultas = $conexion->pretend(fn () => (require database_path('migrations/2026_10_30_000001_create_intenciones_evaluacion_cupo_table.php'))->up());
        } finally {
            Schema::swap($schema);
        }
        $sql = strtolower(implode("\n", array_column($consultas, 'query')));
        $this->assertStringContainsString('engine = innodb', $sql);
        $this->assertStringContainsString('bigint unsigned', $sql);
        $this->assertStringContainsString('unique', $sql);
        $this->assertStringContainsString('`auditoria_cancelacion_id`', $sql);
        $this->assertSame(3, substr_count($sql, 'on delete restrict on update restrict'));
        $this->assertStringNotContainsString('enum(', $sql);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    #[DataProvider('referenciasInvalidas')]
    public function test_database_rejects_nonexistent_references(string $campo): void
    {
        $fila = $this->registrar($this->cancelacion())->fresh()->getAttributes();
        unset($fila['id']);
        $fila['auditoria_cancelacion_id'] = $this->cancelacion()->id;
        $fila[$campo] = 999999;
        $this->expectException(QueryException::class);
        try {
            DB::table('intenciones_evaluacion_cupo')->insert($fila);
        } finally {
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        }
    }

    public static function referenciasInvalidas(): array
    {
        return [['auditoria_cancelacion_id'], ['turno_id'], ['kinesiologa_id']];
    }

    public function test_context_cannot_be_rewritten_or_deleted_and_processed_intention_cannot_reopen(): void
    {
        $intencion = $this->registrar($this->cancelacion());
        foreach (['contexto', 'eliminar', 'reabrir'] as $operacion) {
            $actual = $intencion->fresh();
            if ($operacion === 'reabrir') {
                $actual->procesado_at = $this->ahora->addMinute();
                $actual->save();
                $actual->procesado_at = null;
            } elseif ($operacion === 'contexto') {
                $actual->fin_at = $actual->fin_at->addHour();
            }
            try {
                $operacion === 'eliminar' ? $actual->delete() : $actual->save();
                $this->fail('No debe alterar el hecho persistido.');
            } catch (LogicException) {
                $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
            }
        }
        $this->assertTrue($this->turno->fin_at->equalTo($intencion->fresh()->fin_at));
        $this->assertNotNull($intencion->fresh()->procesado_at);
    }

    private function registrar(Auditoria $cancelacion): IntencionEvaluacionCupo
    {
        return app(RegistrarIntencionEvaluacionCupoService::class)->registrar($cancelacion);
    }

    private function cancelacion(?Turno $turno = null, array $nuevos = [], array $anteriores = [], string $evento = 'turno_cancelado', string $estadoAnterior = 'PENDIENTE', string $canal = 'MIS_TURNOS'): Auditoria
    {
        $turno ??= $this->turno;
        $contexto = ['turno_id' => $turno->id, 'paciente_id' => $turno->paciente_id, 'kinesiologa_id' => $turno->kinesiologa_id,
            'tipo' => $turno->tipo->value, 'inicio_at' => $turno->inicio_at->toIso8601String(), 'fin_at' => $turno->fin_at->toIso8601String()];

        return app(AuditoriaService::class)->registrar(
            evento: $evento, modulo: 'Turnos', auditable: $turno,
            valoresAnteriores: array_replace([...$contexto, 'estado' => $estadoAnterior], $anteriores),
            valoresNuevos: array_replace([...$contexto, 'estado' => 'CANCELADO', 'canal' => $canal,
                'actor_user_id' => $turno->paciente->user_id, 'ocurrido_at' => $this->ahora->toIso8601String()], $nuevos),
            actor: $turno->paciente->user,
        );
    }

    private function rechazoSinIntencion(Auditoria $cancelacion): void
    {
        $antes = $this->turno->fresh()->getAttributes();
        try {
            $this->registrar($cancelacion);
            $this->fail('Debe rechazar la evidencia inválida.');
        } catch (InvalidArgumentException) {
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        }
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
}
