<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Token público para linkear desde el email (único y opcional)
            $table->string('reminder_token', 64)
                ->nullable()
                ->unique()
                ->after('motivo');

            // Cuándo se envió el recordatorio (simulado o real)
            $table->timestamp('reminder_sent_at')
                ->nullable()
                ->after('reminder_token');

            // Estado del recordatorio: simulado | enviado | error (opcional)
            $table->string('reminder_status', 20)
                ->nullable()
                ->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            // Al dropear la columna con índice unique, el índice cae también
            if (Schema::hasColumn('turnos', 'reminder_status')) {
                $table->dropColumn('reminder_status');
            }
            if (Schema::hasColumn('turnos', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
            if (Schema::hasColumn('turnos', 'reminder_token')) {
                $table->dropColumn('reminder_token');
            }
        });
    }
};
