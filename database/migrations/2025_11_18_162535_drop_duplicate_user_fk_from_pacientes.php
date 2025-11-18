<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            // Quitamos la duplicada
            $table->dropForeign('pacientes_user_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            // Si hicieras rollback, volverías a crearla (no necesario en prod)
            $table->foreign('user_id', 'pacientes_user_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }
};
