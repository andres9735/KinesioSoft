<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Solo la borramos si existe
        if (Schema::hasColumn('bloques_disponibilidad', 'deleted_at')) {
            Schema::table('bloques_disponibilidad', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }

    public function down(): void
    {
        // Solo la agregamos si NO existe
        if (! Schema::hasColumn('bloques_disponibilidad', 'deleted_at')) {
            Schema::table('bloques_disponibilidad', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable();
            });
        }
    }
};
