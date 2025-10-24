<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion_rom', function (Blueprint $table) {
            // Asegura tipos correctos (por si vinieron distintos)
            $table->unsignedBigInteger('id_eval_func')->change();
            $table->unsignedBigInteger('id_movimiento')->change();
            $table->unsignedBigInteger('id_metodo')->change();

            // Índices útiles (si no existen)
            $table->index('id_eval_func', 'idx_rom_eval');
            $table->index('id_movimiento', 'idx_rom_mov');
            $table->index('id_metodo', 'idx_rom_met');

            // Agregar FKs (usa nombres explícitos para evitar duplicados)
            $table->foreign('id_eval_func', 'fk_rom_evalfunc')
                  ->references('id_eval_func')->on('evaluacion_funcional')
                  ->cascadeOnDelete();

            $table->foreign('id_movimiento', 'fk_rom_mov')
                  ->references('id_movimiento')->on('movimiento');

            $table->foreign('id_metodo', 'fk_rom_met')
                  ->references('id_metodo')->on('metodo_rom');
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion_rom', function (Blueprint $table) {
            $table->dropForeign('fk_rom_evalfunc');
            $table->dropForeign('fk_rom_mov');
            $table->dropForeign('fk_rom_met');

            $table->dropIndex('idx_rom_eval');
            $table->dropIndex('idx_rom_mov');
            $table->dropIndex('idx_rom_met');
        });
    }
};
