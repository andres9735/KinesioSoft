<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intenciones_evaluacion_cupo', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('auditoria_cancelacion_id')->constrained('auditorias')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->dateTime('liberado_at');
            // NULL = pendiente; una fecha identifica procesamiento efectivo futuro.
            $table->dateTime('procesado_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('auditoria_cancelacion_id', 'int_eval_cupo_cancelacion_unique');
            $table->index('procesado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intenciones_evaluacion_cupo');
    }
};
