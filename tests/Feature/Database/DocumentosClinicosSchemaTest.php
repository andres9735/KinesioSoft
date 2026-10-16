<?php

namespace Tests\Feature\Database;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentosClinicosSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentos_clinicos_has_exactly_the_approved_schema(): void
    {
        $this->assertTableSchema('documentos_clinicos', [
            'id' => 'integer',
            'paciente_id' => 'integer',
            'cargado_por_user_id' => 'integer',
            'origen' => 'varchar',
            'estado_validacion' => 'varchar',
            'tipo_documento_clinico' => 'varchar',
            'nombre_original' => 'varchar',
            'ruta_storage' => 'varchar',
            'mime_type' => 'varchar',
            'tamanio_bytes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ], 'id', ['created_at', 'updated_at']);

        $this->assertForeignKeys('documentos_clinicos', [
            'paciente_id' => 'pacientes',
            'cargado_por_user_id' => 'users',
        ]);
        $this->assertSame([['ruta_storage']], $this->nonPrimaryUniqueColumns('documentos_clinicos'));
    }

    public function test_origen_turno_has_only_its_required_shared_primary_key_and_turno_foreign_key(): void
    {
        $this->assertTableSchema('documentos_clinicos_origen_turno', [
            'documento_clinico_id' => 'integer',
            'turno_id' => 'integer',
        ], 'documento_clinico_id');

        $this->assertForeignKeys('documentos_clinicos_origen_turno', [
            'documento_clinico_id' => 'documentos_clinicos',
            'turno_id' => 'turnos',
        ]);
        $this->assertSame([], $this->nonPrimaryUniqueColumns('documentos_clinicos_origen_turno'));
    }

    public function test_mysql_compilation_preserves_approved_lengths_unsigned_bigints_and_timestamp_defaults(): void
    {
        // SQLite omits VARCHAR lengths and unsignedness; compile the actual migrations offline.
        $connection = new MySqlConnection(
            static fn () => throw new LogicException('La compilación de esquema no debe abrir una conexión.'),
            'schema_test'
        );
        $originalSchema = Schema::getFacadeRoot();
        Schema::swap($connection->getSchemaBuilder());

        try {
            $queries = $connection->pretend(function (): void {
                foreach ($this->documentMigrations() as $migration) {
                    $migration->up();
                }
            });
        } finally {
            Schema::swap($originalSchema);
        }

        $sql = strtolower(implode("\n", array_column($queries, 'query')));
        foreach ([
            '`id` bigint unsigned not null auto_increment primary key',
            '`paciente_id` bigint unsigned not null',
            '`cargado_por_user_id` bigint unsigned not null',
            '`origen` varchar(30) not null',
            '`estado_validacion` varchar(30) not null',
            '`tipo_documento_clinico` varchar(30) not null',
            '`nombre_original` varchar(255) not null',
            '`ruta_storage` varchar(255) not null',
            '`mime_type` varchar(100) not null',
            '`tamanio_bytes` bigint unsigned not null',
            '`created_at` timestamp not null default current_timestamp',
            '`updated_at` timestamp not null default current_timestamp',
            '`documento_clinico_id` bigint unsigned not null',
            '`turno_id` bigint unsigned not null',
            'primary key (`documento_clinico_id`)',
        ] as $definition) {
            $this->assertStringContainsString($definition, $sql);
        }

        $this->assertSame(4, substr_count($sql, 'on delete restrict on update restrict'));
        $this->assertSame(2, substr_count($sql, 'default current_timestamp'));
        $this->assertStringNotContainsString(' enum(', $sql);
        $this->assertStringNotContainsString('check (', $sql);
        preg_match_all('/(?:constraint|unique) `([^`]+)`/', $sql, $matches);
        foreach ($matches[1] as $name) {
            $this->assertLessThanOrEqual(64, strlen($name));
        }
    }

    public function test_database_rejects_duplicate_storage_paths(): void
    {
        $context = $this->context();
        $attributes = $this->documentAttributes($context, 'documentos/prueba.pdf');
        DB::table('documentos_clinicos')->insert($attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed: documentos_clinicos.ruta_storage');

        DB::table('documentos_clinicos')->insert($attributes);
    }

    public function test_database_allows_multiple_documents_per_paciente_uploader_and_turno(): void
    {
        $context = $this->context();
        foreach (['documentos/uno.pdf', 'documentos/dos.pdf'] as $path) {
            $documentId = DB::table('documentos_clinicos')->insertGetId(
                $this->documentAttributes($context, $path)
            );
            DB::table('documentos_clinicos_origen_turno')->insert([
                'documento_clinico_id' => $documentId,
                'turno_id' => $context['turno_id'],
            ]);
        }

        $this->assertSame(2, DB::table('documentos_clinicos')->where('paciente_id', $context['paciente_id'])->count());
        $this->assertSame(2, DB::table('documentos_clinicos')->where('cargado_por_user_id', $context['user_id'])->count());
        $this->assertSame(2, DB::table('documentos_clinicos_origen_turno')->where('turno_id', $context['turno_id'])->count());
    }

    public function test_database_rejects_a_second_turno_origin_for_the_same_document(): void
    {
        $context = $this->context();
        $documentId = DB::table('documentos_clinicos')->insertGetId(
            $this->documentAttributes($context, 'documentos/prueba.pdf')
        );
        $turno = (array) DB::table('turnos')->where('id', $context['turno_id'])->first();
        unset($turno['id']);
        $otherTurnoId = DB::table('turnos')->insertGetId($turno);
        DB::table('documentos_clinicos_origen_turno')->insert([
            'documento_clinico_id' => $documentId,
            'turno_id' => $context['turno_id'],
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed: documentos_clinicos_origen_turno.documento_clinico_id');

        DB::table('documentos_clinicos_origen_turno')->insert([
            'documento_clinico_id' => $documentId,
            'turno_id' => $otherTurnoId,
        ]);
    }

    #[DataProvider('invalidReferences')]
    public function test_database_rejects_invalid_references(string $table, string $column): void
    {
        $context = $this->context();
        $attributes = $this->documentAttributes($context, 'documentos/prueba.pdf');

        if ($table === 'documentos_clinicos_origen_turno') {
            $documentId = DB::table('documentos_clinicos')->insertGetId($attributes);
            $attributes = ['documento_clinico_id' => $documentId, 'turno_id' => $context['turno_id']];
        }
        $attributes[$column] = PHP_INT_MAX;

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');

        DB::table($table)->insert($attributes);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidReferences(): array
    {
        return [
            'paciente' => ['documentos_clinicos', 'paciente_id'],
            'usuario cargador' => ['documentos_clinicos', 'cargado_por_user_id'],
            'documento' => ['documentos_clinicos_origen_turno', 'documento_clinico_id'],
            'turno' => ['documentos_clinicos_origen_turno', 'turno_id'],
        ];
    }

    public function test_file_size_can_preserve_integer_values_larger_than_unsigned_32_bits(): void
    {
        $attributes = $this->documentAttributes($this->context(), 'documentos/prueba.pdf');
        $attributes['tamanio_bytes'] = 4294967296;
        $id = DB::table('documentos_clinicos')->insertGetId($attributes);

        $this->assertSame(4294967296, DB::table('documentos_clinicos')->where('id', $id)->value('tamanio_bytes'));
    }

    public function test_no_out_of_scope_tables_exist(): void
    {
        foreach ([
            'documentos_clinicos_padecimiento', 'slots', 'cupos', 'holds',
            'reservas_temporales', 'solicitud_turno', 'solicitud_turnos', 'solicitudes_turno',
            'staging', 'drafts',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_document_migrations_revert_in_dependency_order_without_altering_existing_tables(): void
    {
        $context = $this->context();
        $before = [];
        foreach (['users', 'pacientes', 'turnos'] as $table) {
            $before[$table] = [
                Schema::getColumns($table), Schema::getIndexes($table),
                Schema::getForeignKeys($table), DB::table($table)->orderBy('id')->get()->all(),
            ];
        }
        [$documents, $origins] = $this->documentMigrations();
        $origins->down();
        $documents->down();

        try {
            $this->assertFalse(Schema::hasTable('documentos_clinicos_origen_turno'));
            $this->assertFalse(Schema::hasTable('documentos_clinicos'));
            foreach ($before as $table => $expected) {
                $this->assertTrue(Schema::hasTable($table));
                $this->assertEquals($expected, [
                    Schema::getColumns($table), Schema::getIndexes($table),
                    Schema::getForeignKeys($table), DB::table($table)->orderBy('id')->get()->all(),
                ]);
            }
            $this->assertDatabaseHas('turnos', ['id' => $context['turno_id']]);
        } finally {
            $documents->up();
            $origins->up();
        }
    }

    /**
     * @param  array<string, string>  $types
     * @param  list<string>  $timestampDefaults
     */
    private function assertTableSchema(string $table, array $types, string $primaryKey, array $timestampDefaults = []): void
    {
        $this->assertTrue(Schema::hasTable($table));
        $columns = collect(DB::select("PRAGMA table_info('{$table}')"))->keyBy('name');
        $this->assertSame(array_keys($types), $columns->keys()->all());
        $this->assertSame([$primaryKey], $columns->filter(static fn (object $column): bool => $column->pk > 0)->keys()->all());

        foreach ($types as $name => $type) {
            $this->assertSame($type, strtolower($columns[$name]->type), $name);
            $this->assertSame(1, $columns[$name]->notnull, $name);
            if (in_array($name, $timestampDefaults, true)) {
                $this->assertSame('CURRENT_TIMESTAMP', strtoupper($columns[$name]->dflt_value));
            } else {
                $this->assertNull($columns[$name]->dflt_value, $name);
            }
        }
    }

    /**
     * @param  array<string, string>  $expected
     */
    private function assertForeignKeys(string $table, array $expected): void
    {
        $keys = collect(DB::select("PRAGMA foreign_key_list('{$table}')"))->keyBy('from');
        $this->assertCount(count($expected), $keys);
        foreach ($expected as $column => $target) {
            $this->assertSame($target, $keys[$column]->table);
            $this->assertSame('id', $keys[$column]->to);
            $this->assertSame('RESTRICT', strtoupper($keys[$column]->on_delete));
            $this->assertSame('RESTRICT', strtoupper($keys[$column]->on_update));
        }
    }

    /**
     * @return list<list<string>>
     */
    private function nonPrimaryUniqueColumns(string $table): array
    {
        return collect(Schema::getIndexes($table))
            ->filter(static fn (array $index): bool => $index['unique'] && ! $index['primary'])
            ->pluck('columns')->values()->all();
    }

    /**
     * @return array{Migration, Migration}
     */
    private function documentMigrations(): array
    {
        return [
            require database_path('migrations/2026_10_16_000002_create_documentos_clinicos_table.php'),
            require database_path('migrations/2026_10_16_000003_create_documentos_clinicos_origen_turno_table.php'),
        ];
    }

    /**
     * @return array{paciente_id: int, user_id: int, turno_id: int}
     */
    private function context(): array
    {
        $userId = User::factory()->create()->id;
        $pacienteId = DB::table('pacientes')->insertGetId([
            'user_id' => $userId, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $kinesiologaId = DB::table('kinesiologas')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'titulo_profesional_id' => null, 'consultorio_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $turnoId = DB::table('turnos')->insertGetId([
            'paciente_id' => $pacienteId, 'kinesiologa_id' => $kinesiologaId,
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA->value, 'estado' => EstadoTurnoEnum::PENDIENTE->value,
            'inicio_at' => '2026-10-20 08:00:00', 'fin_at' => '2026-10-20 08:45:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['paciente_id' => $pacienteId, 'user_id' => $userId, 'turno_id' => $turnoId];
    }

    /**
     * @param  array{paciente_id: int, user_id: int, turno_id: int}  $context
     * @return array<string, mixed>
     */
    private function documentAttributes(array $context, string $path): array
    {
        return [
            'paciente_id' => $context['paciente_id'],
            'cargado_por_user_id' => $context['user_id'],
            'origen' => 'SOLICITUD_TURNO',
            'estado_validacion' => 'PENDIENTE',
            'tipo_documento_clinico' => 'SIN_CLASIFICAR',
            'nombre_original' => 'Estudio.pdf',
            'ruta_storage' => $path,
            'mime_type' => 'application/pdf',
            'tamanio_bytes' => 1024,
        ];
    }
}
