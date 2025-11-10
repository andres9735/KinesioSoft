<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excepciones_disponibilidad', function (Blueprint $table) {
            // Acelera búsquedas por profesional + fecha
            $table->index(['profesional_id', 'fecha'], 'idx_exc_prof_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('excepciones_disponibilidad', function (Blueprint $table) {
            $table->dropIndex('idx_exc_prof_fecha');
        });
    }
};


