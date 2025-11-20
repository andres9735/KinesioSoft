<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Nueva columna (temporal) que referencia al perfil clínico
            $table->unsignedBigInteger('paciente_perfil_id')
                ->nullable()
                ->after('paciente_id');

            $table->index('paciente_perfil_id', 'turnos_paciente_perfil_id_index');
        });

        // Backfill: copiar desde users -> pacientes
        // OJO: pacientes.PK = paciente_id (no "id")
        DB::statement("
            UPDATE turnos t
            JOIN pacientes p ON p.user_id = t.paciente_id
            SET t.paciente_perfil_id = p.paciente_id
        ");

        // FK suave para transición (no bloquea si se borra el perfil): SET NULL
        Schema::table('turnos', function (Blueprint $table) {
            $table->foreign('paciente_perfil_id', 'turnos_paciente_perfil_id_foreign')
                ->references('paciente_id')->on('pacientes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropForeign('turnos_paciente_perfil_id_foreign');
            $table->dropIndex('turnos_paciente_perfil_id_index');
            $table->dropColumn('paciente_perfil_id');
        });
    }
};
