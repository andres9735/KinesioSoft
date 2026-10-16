<?php

namespace Tests\Feature\Database;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TurnoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_turnos_has_the_complete_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('turnos'));
        $this->assertSame([
            'id',
            'paciente_id',
            'kinesiologa_id',
            'tipo',
            'estado',
            'inicio_at',
            'fin_at',
            'motivo_solicitud',
            'recordatorio_enviado_at',
            'created_at',
            'updated_at',
            'clave_idempotencia',
        ], $this->columnNames());

        $this->assertSame(['id'], collect($this->tableInfo())
            ->filter(static fn (object $column): bool => $column->pk > 0)
            ->pluck('name')->all());
        $this->assertColumnType('tipo', 'varchar');
        $this->assertColumnType('estado', 'varchar');
        $this->assertColumnType('inicio_at', 'datetime');
        $this->assertColumnType('fin_at', 'datetime');
        $this->assertColumnType('motivo_solicitud', 'varchar');
        $this->assertColumnType('recordatorio_enviado_at', 'datetime');
        $this->assertColumnType('clave_idempotencia', 'varchar');

        $this->assertRequiredColumns([
            'paciente_id',
            'kinesiologa_id',
            'tipo',
            'estado',
            'inicio_at',
            'fin_at',
            'created_at',
            'updated_at',
        ]);
        $this->assertNullableColumns([
            'motivo_solicitud',
            'recordatorio_enviado_at',
            'clave_idempotencia',
        ]);
        $this->assertColumnsHaveNoDefault([
            'tipo',
            'estado',
            'motivo_solicitud',
            'recordatorio_enviado_at',
            'clave_idempotencia',
        ]);

        $this->assertForeignKey('paciente_id', 'pacientes');
        $this->assertForeignKey('kinesiologa_id', 'kinesiologas');
        $this->assertTemporalIndex();
        $this->assertExpectedIndexes();
    }

    public function test_turnos_does_not_materialize_agenda_or_availability_references(): void
    {
        foreach ([
            'configuracion_agenda_id',
            'franja_horaria_agenda_id',
            'slot_id',
            'cupo_id',
            'hold_id',
            'reserva_id',
            'solicitud_turno_id',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('turnos', $column));
        }

        foreach ([
            'slots',
            'cupos',
            'disponibilidades_materializadas',
            'holds',
            'reservas_temporales',
            'solicitud_turnos',
            'solicitudes_turno',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_turno_rejects_unknown_relations(): void
    {
        $this->expectException(QueryException::class);

        DB::table('turnos')->insert([
            'paciente_id' => PHP_INT_MAX,
            'kinesiologa_id' => PHP_INT_MAX,
            'tipo' => 'SEGUIMIENTO',
            'estado' => 'PENDIENTE',
            'inicio_at' => '2026-10-10 08:00:00',
            'fin_at' => '2026-10-10 08:45:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_duplicate_idempotency_uuids(): void
    {
        $attributes = $this->turnoAttributes('9b969204-2c83-4c42-a956-f4a8482a6f12');
        $id = DB::table('turnos')->insertGetId($attributes);

        $this->assertDatabaseHas('turnos', [
            'id' => $id,
            'clave_idempotencia' => $attributes['clave_idempotencia'],
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed: turnos.clave_idempotencia');

        DB::table('turnos')->insert($attributes);
    }

    public function test_database_allows_multiple_null_idempotency_keys_and_identical_intervals(): void
    {
        $attributes = $this->turnoAttributes(null);
        $firstId = DB::table('turnos')->insertGetId($attributes);
        $secondId = DB::table('turnos')->insertGetId($attributes);

        $this->assertNotSame($firstId, $secondId);
        $this->assertDatabaseCount('turnos', 2);
        $this->assertSame(2, DB::table('turnos')->whereNull('clave_idempotencia')->count());
    }

    public function test_migration_down_preserves_existing_columns_indexes_foreign_keys_and_data(): void
    {
        $id = DB::table('turnos')->insertGetId($this->turnoAttributes(null));
        $before = DB::table('turnos')->where('id', $id)->first();
        unset($before->clave_idempotencia);
        $columns = array_values(array_diff($this->columnNames(), ['clave_idempotencia']));
        $foreignKeys = DB::select("PRAGMA foreign_key_list('turnos')");
        $migration = require database_path(
            'migrations/2026_10_16_000001_add_idempotencia_and_d01_index_to_turnos_table.php'
        );

        $migration->down();

        try {
            $this->assertSame($columns, $this->columnNames());
            $this->assertSame([
                [
                    'name' => 'turnos_kinesiologa_id_inicio_at_fin_at_index',
                    'columns' => ['kinesiologa_id', 'inicio_at', 'fin_at'],
                    'unique' => false,
                    'origin' => 'c',
                ],
            ], $this->indexes());
            $this->assertEquals($foreignKeys, DB::select("PRAGMA foreign_key_list('turnos')"));
            $this->assertEquals($before, DB::table('turnos')->where('id', $id)->first());
        } finally {
            $migration->up();
        }

        $this->assertExpectedIndexes();
        $this->assertDatabaseHas('turnos', ['id' => $id, 'clave_idempotencia' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function turnoAttributes(?string $claveIdempotencia): array
    {
        $pacienteId = DB::table('pacientes')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $kinesiologaId = DB::table('kinesiologas')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'paciente_id' => $pacienteId,
            'kinesiologa_id' => $kinesiologaId,
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA->value,
            'estado' => EstadoTurnoEnum::PENDIENTE->value,
            'inicio_at' => '2026-10-20 08:00:00',
            'fin_at' => '2026-10-20 08:45:00',
            'clave_idempotencia' => $claveIdempotencia,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return list<string>
     */
    private function columnNames(): array
    {
        return array_map(
            static fn (object $column): string => $column->name,
            $this->tableInfo()
        );
    }

    private function assertColumnType(string $column, string $type): void
    {
        $columns = collect($this->tableInfo())->keyBy('name');

        $this->assertSame($type, strtolower($columns[$column]->type));
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertRequiredColumns(array $columns): void
    {
        $tableInfo = collect($this->tableInfo())->keyBy('name');

        foreach ($columns as $column) {
            $this->assertSame(1, $tableInfo[$column]->notnull);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertNullableColumns(array $columns): void
    {
        $tableInfo = collect($this->tableInfo())->keyBy('name');

        foreach ($columns as $column) {
            $this->assertSame(0, $tableInfo[$column]->notnull);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertColumnsHaveNoDefault(array $columns): void
    {
        $tableInfo = collect($this->tableInfo())->keyBy('name');

        foreach ($columns as $column) {
            $this->assertNull($tableInfo[$column]->dflt_value);
        }
    }

    private function assertForeignKey(string $column, string $referencedTable): void
    {
        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('turnos')"))
            ->keyBy('from');

        $this->assertCount(2, $foreignKeys);
        $this->assertSame($referencedTable, $foreignKeys[$column]->table);
        $this->assertSame('id', $foreignKeys[$column]->to);
        $this->assertSame('RESTRICT', strtoupper($foreignKeys[$column]->on_delete));
        $this->assertSame('RESTRICT', strtoupper($foreignKeys[$column]->on_update));
    }

    private function assertTemporalIndex(): void
    {
        $matchingIndexes = array_filter(
            $this->indexes(),
            static fn (array $index): bool => $index['columns'] === [
                'kinesiologa_id',
                'inicio_at',
                'fin_at',
            ] && $index['unique'] === false
        );

        $this->assertCount(1, $matchingIndexes);
    }

    private function assertExpectedIndexes(): void
    {
        $this->assertSame([
            [
                'name' => 'turnos_clave_idempotencia_unique',
                'columns' => ['clave_idempotencia'],
                'unique' => true,
                'origin' => 'c',
            ],
            [
                'name' => 'turnos_kinesiologa_id_inicio_at_fin_at_index',
                'columns' => ['kinesiologa_id', 'inicio_at', 'fin_at'],
                'unique' => false,
                'origin' => 'c',
            ],
            [
                'name' => 'turnos_paciente_id_tipo_estado_inicio_at_index',
                'columns' => ['paciente_id', 'tipo', 'estado', 'inicio_at'],
                'unique' => false,
                'origin' => 'c',
            ],
        ], collect($this->indexes())->sortBy('name')->values()->all());
    }

    /**
     * @return list<object>
     */
    private function tableInfo(): array
    {
        return DB::select("PRAGMA table_info('turnos')");
    }

    /**
     * @return list<array{name: string, columns: list<string>, unique: bool, origin: string}>
     */
    private function indexes(): array
    {
        return array_map(function (object $index): array {
            $indexName = str_replace("'", "''", $index->name);
            $columns = DB::select("PRAGMA index_info('{$indexName}')");

            return [
                'name' => $index->name,
                'columns' => array_map(
                    static fn (object $column): string => $column->name,
                    $columns
                ),
                'unique' => (bool) $index->unique,
                'origin' => $index->origin,
            ];
        }, DB::select("PRAGMA index_list('turnos')"));
    }
}
