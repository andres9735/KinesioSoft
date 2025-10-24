<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bloques_disponibilidad', function (Blueprint $table) {
            // 1) Soft deletes
            if (!Schema::hasColumn('bloques_disponibilidad', 'deleted_at')) {
                $table->softDeletes();
            }

            // 2) Asegurar índice para el FK antes de tirar el UNIQUE compuesto
            //    (si ya existe un índice simple, MySQL lo ignora)
            $table->index('profesional_id', 'idx_bloq_profesional_id');

            // 3) Reemplazar el UNIQUE original por otro que incluya deleted_at
            //    para permitir recrear bloques iguales si el anterior está soft-deleted.
            try {
                $table->dropUnique('uq_prof_dia_tramo'); // tu nombre original
            } catch (\Throwable $e) {
                // Ignorar si ya no existe, evita que falle en entornos divergentes
            }

            $table->unique(
                ['profesional_id', 'dia_semana', 'hora_desde', 'hora_hasta', 'deleted_at'],
                'uq_prof_dia_tramo_active'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bloques_disponibilidad', function (Blueprint $table) {
            // Volver al UNIQUE original (sin deleted_at)
            try {
                $table->dropUnique('uq_prof_dia_tramo_active');
            } catch (\Throwable $e) {
                // ignore
            }

            $table->unique(
                ['profesional_id', 'dia_semana', 'hora_desde', 'hora_hasta'],
                'uq_prof_dia_tramo'
            );

            // Quitar el índice auxiliar si querés dejar todo como antes
            try {
                $table->dropIndex('idx_bloq_profesional_id');
            } catch (\Throwable $e) {
                // ignore
            }

            // Quitar soft deletes
            if (Schema::hasColumn('bloques_disponibilidad', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
