<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Flag para saber si el turno se generó por adelanto automático
            $table->boolean('es_adelanto_automatico')
                ->default(false)
                ->after('motivo'); // lo podés mover donde prefieras

            $table->index('es_adelanto_automatico');
        });

        // Opcional: marcar como adelanto los turnos que ya fueron generados
        // por tu módulo (si ya tenés algunos de prueba)
        DB::table('turnos')
            ->where('motivo', 'Turno generado por adelanto automático')
            ->update(['es_adelanto_automatico' => true]);
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropIndex(['es_adelanto_automatico']);
            $table->dropColumn('es_adelanto_automatico');
        });
    }
};
