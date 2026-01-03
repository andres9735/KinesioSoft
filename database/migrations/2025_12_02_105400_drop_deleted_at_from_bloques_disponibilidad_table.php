<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

return new class extends Migration {
    public function up(): void
    {
        try {
            Schema::table('bloques_disponibilidad', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        } catch (QueryException $e) {
            // 1072 = Key column doesn't exist (MySQL)
            // Si no existe, ignoramos y seguimos.
            if ((int) ($e->errorInfo[1] ?? 0) !== 1072) {
                throw $e;
            }
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
