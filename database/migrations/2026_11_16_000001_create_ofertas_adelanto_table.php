<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofertas_adelanto', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('intencion_evaluacion_cupo_id')->constrained('intenciones_evaluacion_cupo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete()->restrictOnUpdate();
            // La reserva ocupa el cupo completo, no sólo el destino del candidato.
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->dateTime('destino_fin_at');
            $table->dateTime('origen_inicio_at');
            $table->dateTime('origen_fin_at');
            $table->string('estado', 32);
            $table->dateTime('activada_at', 6);
            $table->dateTime('vence_at', 6);
            $table->dateTime('cerrada_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['intencion_evaluacion_cupo_id', 'paciente_id'], 'oferta_cupo_paciente_unique');
            $table->index(['kinesiologa_id', 'estado', 'inicio_at'], 'oferta_reserva_agenda_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofertas_adelanto');
    }
};
