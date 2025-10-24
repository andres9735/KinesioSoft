<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Elimina sólo si existen (para no romper en distintos entornos)
        $this->dropFkIfExists('evaluacion_rom', 'evaluacion_rom_id_eval_func_foreign');
        $this->dropFkIfExists('evaluacion_rom', 'evaluacion_rom_id_metodo_foreign');
        $this->dropFkIfExists('evaluacion_rom', 'evaluacion_rom_id_movimiento_foreign');
    }

    public function down(): void
    {
        // Volver a crear las FKs con los nombres "auto" por si se hace rollback.
        Schema::table('evaluacion_rom', function (Blueprint $table) {
            $table->foreign('id_eval_func')
                ->references('id_eval_func')->on('evaluacion_funcional')
                ->cascadeOnDelete();

            $table->foreign('id_metodo')
                ->references('id_metodo')->on('metodo_rom');

            $table->foreign('id_movimiento')
                ->references('id_movimiento')->on('movimiento');
        });
    }

    private function dropFkIfExists(string $table, string $constraint): void
    {
        $exists = DB::selectOne("
            SELECT CONSTRAINT_NAME
              FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $constraint]);

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }
};
