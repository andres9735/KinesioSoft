<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Promedio de valoración (0–5)
            $table->decimal('rating_avg', 3, 2)->default(0)->after('specialty');
            // Cantidad de valoraciones recibidas
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
    }
};
