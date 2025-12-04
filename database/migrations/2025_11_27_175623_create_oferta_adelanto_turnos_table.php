<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oferta_adelanto_turnos', function (Blueprint $table) {
            $table->id();

            // Turno que quedó libre (cancelado con suficiente anticipación)
            $table->foreignId('turno_ofertado_id')
                ->constrained('turnos', 'id_turno')
                ->cascadeOnDelete();

            // Turno original del paciente al que se le ofrece adelantar
            $table->foreignId('turno_original_paciente_id')
                ->constrained('turnos', 'id_turno')
                ->cascadeOnDelete();

            // Turno nuevo generado si el paciente acepta el adelanto
            $table->foreignId('turno_resultante_id')
                ->nullable()
                ->constrained('turnos', 'id_turno')
                ->cascadeOnDelete();

            // Redundancia controlada para facilitar consultas
            $table->foreignId('profesional_id')
                ->constrained('users'); // mismo profesional que los turnos

            $table->foreignId('paciente_id')
                ->constrained('users'); // paciente que recibe la oferta

            $table->foreignId('paciente_perfil_id')
                ->nullable()
                ->constrained('pacientes', 'paciente_id')
                ->nullOnDelete();

            // Estado del ciclo de vida de la oferta
            $table->enum('estado', [
                'pendiente',
                'aceptada',
                'rechazada',
                'sin_respuesta',
                'cancelada_sistema',
                'expirada',
            ])->default('pendiente');

            // Orden de la cola de candidatos para el mismo turno_ofertado
            $table->unsignedTinyInteger('orden_cola')->default(1);

            // Token para el link del mail
            $table->string('oferta_token', 64)->nullable();

            // Tiempos del flujo
            $table->timestamp('oferta_enviada_at')->nullable();
            $table->timestamp('fecha_limite_respuesta')->nullable();
            $table->timestamp('respondida_at')->nullable();

            $table->timestamps();

            // Índices
            $table->unique('oferta_token');
            $table->index(['turno_ofertado_id', 'estado']);
            $table->index(['estado', 'fecha_limite_respuesta']);
            $table->index('paciente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oferta_adelanto_turnos');
    }
};
