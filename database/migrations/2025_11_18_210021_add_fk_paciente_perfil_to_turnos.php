<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ¿ya existe alguna FK para la columna?
        $db = DB::getDatabaseName();
        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'turnos')
            ->where('COLUMN_NAME', 'paciente_perfil_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if ($exists) {
            // Ya hay una FK; no hacemos nada para evitar el error 121.
            return;
        }

        Schema::table('turnos', function (Blueprint $table) {
            // SOLO agregamos la FK con un nombre único
            $table->foreign('paciente_perfil_id', 'fk_turnos_pac_perfil_20251118a')
                ->references('paciente_id')->on('pacientes')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // Por si la FK existe con nuestro nombre; si no, no rompe.
        try {
            Schema::table('turnos', function (Blueprint $table) {
                $table->dropForeign('fk_turnos_pac_perfil_20251118a');
            });
        } catch (\Throwable $e) {
            // noop
        }
    }
};
