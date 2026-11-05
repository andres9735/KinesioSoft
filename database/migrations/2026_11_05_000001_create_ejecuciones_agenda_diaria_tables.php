<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejecuciones_agenda_diaria', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            // Identifica la ejecución, no una cita, recordatorio ni transición de asistencia.
            $table->uuid('clave_ejecucion')->unique('ejec_agenda_clave_unique');
            $table->date('fecha_local');
            $table->string('zona_horaria', 64);
            $table->string('resultado', 20);
            $table->dateTime('iniciado_at', 6);
            $table->dateTime('finalizado_at', 6)->nullable();
            $table->string('codigo_incidencia', 40)->nullable();
            // No UNIQUE por fecha: ejecuciones distintas deben conservar su trazabilidad.
            $table->index(['fecha_local', 'resultado'], 'ejec_agenda_fecha_resultado_idx');
        });

        Schema::create('preparaciones_agenda_diaria', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('ejecucion_id')->constrained('ejecuciones_agenda_diaria')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete()->restrictOnUpdate();
            $table->string('resultado', 20);
            $table->dateTime('iniciado_at', 6);
            $table->dateTime('finalizado_at', 6)->nullable();
            // NULL sin resultado; cero sólo tras preparar realmente una jornada vacía.
            $table->unsignedInteger('total_turnos')->nullable();
            $table->unsignedInteger('turnos_operativos')->nullable();
            $table->unsignedInteger('turnos_en_atencion')->nullable();
            $table->unsignedInteger('otros_turnos')->nullable();
            $table->string('codigo_incidencia', 40)->nullable();
            $table->unique(['ejecucion_id', 'kinesiologa_id'], 'prep_agenda_ejec_prof_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparaciones_agenda_diaria');
        Schema::dropIfExists('ejecuciones_agenda_diaria');
    }
};
