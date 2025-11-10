<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // 1) Crear la columna generada SOLO si no existe
        if (!Schema::hasColumn('turnos', 'pendiente_guard')) {
            DB::statement("
                ALTER TABLE turnos
                ADD COLUMN pendiente_guard TINYINT
                AS (CASE WHEN estado = 'pendiente' THEN 1 ELSE NULL END) STORED
            ");
        }

        // (Opcional) si todavía existe la vieja 'pendiente_flag' y ya no la usás, la borramos con try/catch
        try {
            if (Schema::hasColumn('turnos', 'pendiente_flag')) {
                DB::statement("ALTER TABLE turnos DROP COLUMN pendiente_flag");
            }
        } catch (\Throwable $e) {
            // ignorar si no se puede borrar en esta versión de MariaDB/MySQL
        }

        // 2) Eliminar duplicados que impedirían el UNIQUE (solo aplica cuando ambos están 'pendiente')
        //    Deja el registro con menor id_turno
        DB::statement("
            DELETE t1 FROM turnos t1
            JOIN turnos t2
              ON t1.id_turno > t2.id_turno
             AND t1.paciente_id = t2.paciente_id
             AND t1.profesional_id = t2.profesional_id
             AND t1.estado = 'pendiente'
             AND t2.estado = 'pendiente'
        ");

        // 3) Crear el índice único SOLO si no existe
        $idxExists = DB::select("
            SELECT 1
              FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'turnos'
               AND INDEX_NAME = 'uq_turnos_pac_prof_pendiente'
             LIMIT 1
        ");

        if (empty($idxExists)) {
            DB::statement("
                ALTER TABLE turnos
                ADD CONSTRAINT uq_turnos_pac_prof_pendiente
                UNIQUE KEY (paciente_id, profesional_id, pendiente_guard)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        // Borrar UNIQUE si existe
        try {
            DB::statement("ALTER TABLE turnos DROP INDEX uq_turnos_pac_prof_pendiente");
        } catch (\Throwable $e) {}

        // Borrar la columna generada si existe
        try {
            if (Schema::hasColumn('turnos', 'pendiente_guard')) {
                DB::statement("ALTER TABLE turnos DROP COLUMN pendiente_guard");
            }
        } catch (\Throwable $e) {}
    }
};

