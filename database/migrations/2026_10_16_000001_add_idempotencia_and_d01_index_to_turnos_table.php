<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->uuid('clave_idempotencia')->nullable()->unique();
            $table->index(['paciente_id', 'tipo', 'estado', 'inicio_at']);
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropIndex(['paciente_id', 'tipo', 'estado', 'inicio_at']);
            $table->dropUnique(['clave_idempotencia']);
            $table->dropColumn('clave_idempotencia');
        });
    }
};
