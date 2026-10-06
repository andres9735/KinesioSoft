<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
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
        ], $this->columnNames());

        $this->assertColumnType('tipo', 'varchar');
        $this->assertColumnType('estado', 'varchar');
        $this->assertColumnType('inicio_at', 'datetime');
        $this->assertColumnType('fin_at', 'datetime');
        $this->assertColumnType('motivo_solicitud', 'varchar');
        $this->assertColumnType('recordatorio_enviado_at', 'datetime');

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
        ]);
        $this->assertColumnsHaveNoDefault([
            'tipo',
            'estado',
            'motivo_solicitud',
            'recordatorio_enviado_at',
        ]);

        $this->assertForeignKey('paciente_id', 'pacientes');
        $this->assertForeignKey('kinesiologa_id', 'kinesiologas');
        $this->assertTemporalIndex();
        $this->assertNoNonPrimaryUniqueIndexes();
    }

    public function test_turnos_does_not_materialize_agenda_or_availability_references(): void
    {
        foreach ([
            'configuracion_agenda_id',
            'franja_horaria_agenda_id',
            'slot_id',
            'cupo_id',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('turnos', $column));
        }

        foreach (['slots', 'cupos', 'disponibilidades_materializadas'] as $table) {
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

    private function assertNoNonPrimaryUniqueIndexes(): void
    {
        $uniqueIndexes = array_filter(
            $this->indexes(),
            static fn (array $index): bool => $index['unique'] === true
                && $index['origin'] !== 'pk'
        );

        $this->assertSame([], array_values($uniqueIndexes));
    }

    /**
     * @return list<object>
     */
    private function tableInfo(): array
    {
        return DB::select("PRAGMA table_info('turnos')");
    }

    /**
     * @return list<array{columns: list<string>, unique: bool, origin: string}>
     */
    private function indexes(): array
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
        }, DB::select("PRAGMA index_list('turnos')"));
    }
}
