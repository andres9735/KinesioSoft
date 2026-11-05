<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas_generacion_agenda', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->date('fecha_local');
            $table->string('zona_horaria', 64);
            $table->foreignId('ejecucion_actual_id')->nullable()->constrained('ejecuciones_agenda_diaria')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('reclamo_hasta', 6)->nullable();
            $table->unique(['fecha_local', 'zona_horaria'], 'jornada_agenda_fecha_zona_unique');
        });
        Schema::table('preparaciones_agenda_diaria', function (Blueprint $table): void {
            // Reutilización explícita de un resultado previo; no se vuelve a preparar la cita.
            $table->foreignId('preparacion_origen_id')->nullable()->constrained('preparaciones_agenda_diaria')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('preparaciones_agenda_diaria', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preparacion_origen_id');
        });
        Schema::dropIfExists('jornadas_generacion_agenda');
    }
};
