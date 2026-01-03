<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->enum('estado', ['borrador', 'finalizada'])
                ->default('borrador')
                ->after('tipo'); // ajustá el "after" a una columna existente

            $table->unsignedTinyInteger('paso_actual')
                ->default(1)
                ->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropColumn(['estado', 'paso_actual']);
        });
    }
};
