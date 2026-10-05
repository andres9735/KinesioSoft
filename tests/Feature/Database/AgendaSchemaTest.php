<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuraciones_agenda_has_the_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('configuraciones_agenda'));
        $this->assertSame([
            'id',
            'kinesiologa_id',
            'vigencia_desde',
            'vigencia_hasta',
            'duracion_turno_minutos',
            'anulado_at',
            'motivo_anulacion',
            'created_at',
            'updated_at',
        ], $this->columnNames('configuraciones_agenda'));

        $this->assertColumnType('configuraciones_agenda', 'vigencia_desde', 'date');
        $this->assertColumnType('configuraciones_agenda', 'vigencia_hasta', 'date');
        $this->assertColumnType('configuraciones_agenda', 'duracion_turno_minutos', 'integer');
        $this->assertColumnType('configuraciones_agenda', 'anulado_at', 'datetime');
        $this->assertNullableColumns('configuraciones_agenda', [
            'vigencia_hasta',
            'anulado_at',
            'motivo_anulacion',
        ]);
        $this->assertRequiredColumns('configuraciones_agenda', [
            'kinesiologa_id',
            'vigencia_desde',
            'duracion_turno_minutos',
            'created_at',
            'updated_at',
        ]);
        $this->assertForeignKey(
            'configuraciones_agenda',
            'kinesiologa_id',
            'kinesiologas',
            'id'
        );
        $this->assertIndex(
            'configuraciones_agenda',
            ['kinesiologa_id', 'anulado_at', 'vigencia_desde', 'vigencia_hasta']
        );
        $this->assertNoNonPrimaryUniqueIndexes('configuraciones_agenda');
        $this->assertFalse(Schema::hasColumn('configuraciones_agenda', 'estado'));
    }

    public function test_franjas_horarias_agenda_has_the_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('franjas_horarias_agenda'));
        $this->assertSame([
            'id',
            'configuracion_agenda_id',
            'dia_semana',
            'hora_inicio',
            'hora_fin',
            'created_at',
            'updated_at',
        ], $this->columnNames('franjas_horarias_agenda'));

        $this->assertColumnType('franjas_horarias_agenda', 'dia_semana', 'integer');
        $this->assertColumnType('franjas_horarias_agenda', 'hora_inicio', 'time');
        $this->assertColumnType('franjas_horarias_agenda', 'hora_fin', 'time');
        $this->assertRequiredColumns('franjas_horarias_agenda', [
            'configuracion_agenda_id',
            'dia_semana',
            'hora_inicio',
            'hora_fin',
            'created_at',
            'updated_at',
        ]);
        $this->assertForeignKey(
            'franjas_horarias_agenda',
            'configuracion_agenda_id',
            'configuraciones_agenda',
            'id'
        );
        $this->assertIndex(
            'franjas_horarias_agenda',
            ['configuracion_agenda_id', 'dia_semana']
        );
        $this->assertNoNonPrimaryUniqueIndexes('franjas_horarias_agenda');

        foreach (['estado', 'activo', 'dia_habilitado'] as $column) {
            $this->assertFalse(Schema::hasColumn('franjas_horarias_agenda', $column));
        }
    }

    public function test_bloqueos_agenda_has_the_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('bloqueos_agenda'));
        $this->assertSame([
            'id',
            'kinesiologa_id',
            'inicio_at',
            'fin_at',
            'motivo',
            'anulado_at',
            'motivo_anulacion',
            'created_at',
            'updated_at',
        ], $this->columnNames('bloqueos_agenda'));

        $this->assertColumnType('bloqueos_agenda', 'inicio_at', 'datetime');
        $this->assertColumnType('bloqueos_agenda', 'fin_at', 'datetime');
        $this->assertColumnType('bloqueos_agenda', 'anulado_at', 'datetime');
        $this->assertNullableColumns('bloqueos_agenda', [
            'anulado_at',
            'motivo_anulacion',
        ]);
        $this->assertRequiredColumns('bloqueos_agenda', [
            'kinesiologa_id',
            'inicio_at',
            'fin_at',
            'motivo',
            'created_at',
            'updated_at',
        ]);
        $this->assertForeignKey(
            'bloqueos_agenda',
            'kinesiologa_id',
            'kinesiologas',
            'id'
        );
        $this->assertIndex(
            'bloqueos_agenda',
            ['kinesiologa_id', 'anulado_at', 'inicio_at', 'fin_at']
        );
        $this->assertNoNonPrimaryUniqueIndexes('bloqueos_agenda');

        foreach ([
            'configuracion_agenda_id',
            'estado',
            'activo',
            'tipo',
            'todo_el_dia',
            'creado_por_user_id',
            'anulado_por_user_id',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('bloqueos_agenda', $column));
        }
    }

    public function test_configuracion_agenda_rejects_an_unknown_kinesiologa(): void
    {
        $this->expectException(QueryException::class);

        DB::table('configuraciones_agenda')->insert(
            $this->configuracionAttributes(PHP_INT_MAX)
        );
    }

    public function test_franja_horaria_rejects_an_unknown_configuracion(): void
    {
        $this->expectException(QueryException::class);

        DB::table('franjas_horarias_agenda')->insert([
            'configuracion_agenda_id' => PHP_INT_MAX,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_bloqueo_agenda_rejects_an_unknown_kinesiologa(): void
    {
        $this->expectException(QueryException::class);

        DB::table('bloqueos_agenda')->insert(
            $this->bloqueoAttributes(PHP_INT_MAX)
        );
    }

    public function test_configuracion_cannot_be_deleted_while_it_has_franjas(): void
    {
        $configuracionId = $this->createConfiguracion($this->createKinesiologa());

        $franjaId = DB::table('franjas_horarias_agenda')->insertGetId([
            'configuracion_agenda_id' => $configuracionId,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDeleteIsRestricted('configuraciones_agenda', $configuracionId);
        $this->assertDatabaseHas('franjas_horarias_agenda', ['id' => $franjaId]);
    }

    public function test_kinesiologa_cannot_be_deleted_while_it_has_a_configuracion(): void
    {
        $kinesiologaId = $this->createKinesiologa();
        $configuracionId = $this->createConfiguracion($kinesiologaId);

        $this->assertDeleteIsRestricted('kinesiologas', $kinesiologaId);
        $this->assertDatabaseHas('configuraciones_agenda', ['id' => $configuracionId]);
    }

    public function test_kinesiologa_cannot_be_deleted_while_it_has_a_bloqueo(): void
    {
        $kinesiologaId = $this->createKinesiologa();
        $bloqueoId = DB::table('bloqueos_agenda')->insertGetId(
            $this->bloqueoAttributes($kinesiologaId)
        );

        $this->assertDeleteIsRestricted('kinesiologas', $kinesiologaId);
        $this->assertDatabaseHas('bloqueos_agenda', ['id' => $bloqueoId]);
    }

    /**
     * @return list<string>
     */
    private function columnNames(string $table): array
    {
        return array_map(
            static fn (object $column): string => $column->name,
            $this->tableInfo($table)
        );
    }

    private function assertColumnType(string $table, string $column, string $type): void
    {
        $columns = collect($this->tableInfo($table))->keyBy('name');

        $this->assertSame($type, strtolower($columns[$column]->type));
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertNullableColumns(string $table, array $columns): void
    {
        $tableInfo = collect($this->tableInfo($table))->keyBy('name');

        foreach ($columns as $column) {
            $this->assertSame(0, $tableInfo[$column]->notnull);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertRequiredColumns(string $table, array $columns): void
    {
        $tableInfo = collect($this->tableInfo($table))->keyBy('name');

        foreach ($columns as $column) {
            $this->assertSame(1, $tableInfo[$column]->notnull);
        }
    }

    private function assertForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        $foreignKeys = $this->foreignKeys($table);

        $this->assertCount(1, $foreignKeys);
        $this->assertSame($column, $foreignKeys[0]->from);
        $this->assertSame($referencedTable, $foreignKeys[0]->table);
        $this->assertSame($referencedColumn, $foreignKeys[0]->to);
        $this->assertSame('RESTRICT', strtoupper($foreignKeys[0]->on_delete));
        $this->assertSame('RESTRICT', strtoupper($foreignKeys[0]->on_update));
    }

    /**
     * @param  list<string>  $expectedColumns
     */
    private function assertIndex(string $table, array $expectedColumns): void
    {
        $matchingIndexes = array_filter(
            $this->indexes($table),
            static fn (array $index): bool => $index['columns'] === $expectedColumns
                && $index['unique'] === false
        );

        $this->assertCount(1, $matchingIndexes);
    }

    private function assertNoNonPrimaryUniqueIndexes(string $table): void
    {
        $uniqueIndexes = array_filter(
            $this->indexes($table),
            static fn (array $index): bool => $index['unique'] === true
                && $index['origin'] !== 'pk'
        );

        $this->assertSame([], array_values($uniqueIndexes));
    }

    /**
     * @return list<object>
     */
    private function tableInfo(string $table): array
    {
        return DB::select("PRAGMA table_info('{$table}')");
    }

    /**
     * @return list<object>
     */
    private function foreignKeys(string $table): array
    {
        return DB::select("PRAGMA foreign_key_list('{$table}')");
    }

    /**
     * @return list<array{columns: list<string>, unique: bool, origin: string}>
     */
    private function indexes(string $table): array
    {
        return array_map(function (object $index): array {
            $indexName = str_replace("'", "''", $index->name);
            $columns = DB::select("PRAGMA index_info('{$indexName}')");

            return [
                'columns' => array_map(
                    static fn (object $column): string => $column->name,
                    $columns
                ),
                'unique' => (bool) $index->unique,
                'origin' => $index->origin,
            ];
        }, DB::select("PRAGMA index_list('{$table}')"));
    }

    private function createKinesiologa(): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Kinesióloga de esquema',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('kinesiologas')->insertGetId([
            'user_id' => $userId,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createConfiguracion(int $kinesiologaId): int
    {
        return DB::table('configuraciones_agenda')->insertGetId(
            $this->configuracionAttributes($kinesiologaId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function configuracionAttributes(int $kinesiologaId): array
    {
        return [
            'kinesiologa_id' => $kinesiologaId,
            'vigencia_desde' => '2026-10-06',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'anulado_at' => null,
            'motivo_anulacion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bloqueoAttributes(int $kinesiologaId): array
    {
        return [
            'kinesiologa_id' => $kinesiologaId,
            'inicio_at' => '2026-10-10 08:00:00',
            'fin_at' => '2026-10-10 12:00:00',
            'motivo' => 'Bloqueo de prueba estructural',
            'anulado_at' => null,
            'motivo_anulacion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function assertDeleteIsRestricted(string $table, int $id): void
    {
        try {
            DB::table($table)->where('id', $id)->delete();

            $this->fail("Se esperaba que la base de datos impidiera eliminar {$table}.");
        } catch (QueryException) {
            $this->assertDatabaseHas($table, ['id' => $id]);
        }
    }
}
