<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Antes de endurecer el morph, toda auditoría histórica
         * debe poseer un objetivo identificable.
         */
        $auditoriasSinObjetivo = DB::table('auditorias')
            ->where(function ($query): void {
                $query
                    ->whereNull('auditable_type')
                    ->orWhereNull('auditable_id');
            })
            ->get([
                'id',
                'actor_user_id',
                'evento',
            ]);

        foreach ($auditoriasSinObjetivo as $auditoria) {
            if (
                $auditoria->evento !== 'auditoria_exportada_pdf'
                || $auditoria->actor_user_id === null
            ) {
                throw new \RuntimeException(
                    'Existe una auditoría histórica sin objetivo '
                        . 'que no puede migrarse automáticamente. '
                        . 'Auditoría #' . $auditoria->id . '.'
                );
            }

            DB::table('auditorias')
                ->where('id', $auditoria->id)
                ->update([
                    'auditable_type' => (new \App\Models\User())->getMorphClass(),
                    'auditable_id' => $auditoria->actor_user_id,
                ]);
        }

        if (
            DB::table('auditorias')
            ->where(function ($query): void {
                $query
                    ->whereNull('auditable_type')
                    ->orWhereNull('auditable_id');
            })
            ->exists()
        ) {
            throw new \RuntimeException(
                'No fue posible asignar un objetivo a todas las auditorías históricas.'
            );
        }

        /*
         * MR.11 elimina metadata. Sólo es posible trasladarla
         * automáticamente cuando valores_nuevos está vacío.
         */
        $existenConflictosMetadata = DB::table('auditorias')
            ->whereNotNull('metadata')
            ->whereNotNull('valores_nuevos')
            ->exists();

        if ($existenConflictosMetadata) {
            throw new \RuntimeException(
                'Existen auditorías con metadata y valores_nuevos '
                    . 'simultáneamente. La migración requiere revisión.'
            );
        }

        DB::table('auditorias')
            ->whereNotNull('metadata')
            ->whereNull('valores_nuevos')
            ->update([
                'valores_nuevos' => DB::raw('metadata'),
            ]);

        /*
         * Nombres definitivos de los snapshots del actor.
         */
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->renameColumn(
                'actor_nombre',
                'actor_nombre_snapshot'
            );
        });

        Schema::table('auditorias', function (Blueprint $table): void {
            $table->renameColumn(
                'actor_email',
                'actor_email_snapshot'
            );
        });

        /*
         * Contexto de navegación según MR.11.
         */
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->text('url')
                ->nullable()
                ->after('valores_nuevos');
        });

        /*
         * El target de una auditoría deja de ser opcional.
         */
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->string('auditable_type')
                ->nullable(false)
                ->change();

            $table->unsignedBigInteger('auditable_id')
                ->nullable(false)
                ->change();
        });

        /*
         * metadata ya fue conservada en valores_nuevos.
         */
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }

    public function down(): void
    {
        /*
         * Primero restauramos la estructura permisiva anterior.
         */
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->string('auditable_type')
                ->nullable()
                ->change();

            $table->unsignedBigInteger('auditable_id')
                ->nullable()
                ->change();
        });

        /*
         * Restauramos la semántica histórica de las exportaciones:
         * antes no tenían target auditable.
         */
        DB::table('auditorias')
            ->where('evento', 'auditoria_exportada_pdf')
            ->update([
                'auditable_type' => null,
                'auditable_id' => null,
            ]);

        Schema::table('auditorias', function (Blueprint $table): void {
            $table->json('metadata')
                ->nullable()
                ->after('valores_nuevos');
        });

        /*
         * En el esquema anterior, el contexto de exportación
         * pertenecía a metadata.
         */
        DB::table('auditorias')
            ->where('evento', 'auditoria_exportada_pdf')
            ->whereNotNull('valores_nuevos')
            ->update([
                'metadata' => DB::raw('valores_nuevos'),
                'valores_nuevos' => null,
            ]);

        Schema::table('auditorias', function (Blueprint $table): void {
            $table->dropColumn('url');
        });

        Schema::table('auditorias', function (Blueprint $table): void {
            $table->renameColumn(
                'actor_nombre_snapshot',
                'actor_nombre'
            );
        });

        Schema::table('auditorias', function (Blueprint $table): void {
            $table->renameColumn(
                'actor_email_snapshot',
                'actor_email'
            );
        });
    }
};
