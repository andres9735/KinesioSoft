<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paciente_id');
            $table->foreignId('kinesiologa_id');
            $table->string('tipo', 30);
            $table->string('estado', 30);
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->string('motivo_solicitud', 500)->nullable();
            $table->dateTime('recordatorio_enviado_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index([
                'kinesiologa_id',
                'inicio_at',
                'fin_at',
            ]);

            $table->foreign('paciente_id')
                ->references('id')
                ->on('pacientes')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('kinesiologa_id')
                ->references('id')
                ->on('kinesiologas')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
