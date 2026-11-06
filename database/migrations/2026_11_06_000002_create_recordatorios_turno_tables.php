<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios_turno', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('identidad')->unique();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('version_programacion');
            $table->string('tipo', 30);
            $table->dateTime('inicio_at', 6);
            $table->dateTime('fin_at', 6);
            $table->string('zona_horaria', 64);
            $table->string('destinatario', 254)->nullable();
            $table->string('estado', 20);
            $table->string('incidencia', 40)->nullable();
            $table->foreignId('enlace_id')->nullable()->unique()->constrained('enlaces_asistencia_turno')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sobre_id')->nullable()->unique()->constrained('sobres_entrega_enlace')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('creado_at', 6);
            $table->dateTime('actualizado_at', 6);
            $table->unique(['turno_id', 'version_programacion'], 'recordatorio_turno_version_unique');
            $table->index(['estado', 'inicio_at']);
        });
        Schema::create('intentos_recordatorio_turno', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('recordatorio_id')->constrained('recordatorios_turno')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('identidad')->unique();
            $table->unsignedSmallInteger('numero');
            $table->string('destinatario', 254);
            $table->string('resultado', 30);
            $table->string('incidencia', 40)->nullable();
            // Referencia técnica verificable, nunca respuesta SMTP/URL o mensaje libre.
            $table->uuid('evidencia_entrega')->nullable();
            $table->dateTime('iniciado_at', 6);
            $table->dateTime('resuelto_at', 6)->nullable();
            $table->unique(['recordatorio_id', 'numero'], 'intento_recordatorio_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_recordatorio_turno');
        Schema::dropIfExists('recordatorios_turno');
    }
};
