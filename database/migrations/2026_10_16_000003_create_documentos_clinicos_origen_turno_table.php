<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_clinicos_origen_turno', function (Blueprint $table): void {
            $table->foreignId('documento_clinico_id')->primary();
            $table->foreignId('turno_id');

            $table->foreign('documento_clinico_id')
                ->references('id')
                ->on('documentos_clinicos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('turno_id')
                ->references('id')
                ->on('turnos')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_clinicos_origen_turno');
    }
};
