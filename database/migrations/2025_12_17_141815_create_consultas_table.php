<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas', function (Blueprint $table) {
            $table->bigIncrements('id_consulta');

            // 1 consulta por turno (clave para Etapa 1)
            $table->unsignedBigInteger('turno_id')->unique();

            // Los vas a filtrar mucho en historia clínica / agenda
            $table->unsignedBigInteger('paciente_id')->index();
            $table->unsignedBigInteger('kinesiologa_id')->index();

            // Fecha de la consulta (para listados, reportes, etc.)
            $table->date('fecha')->index();

            $table->string('tipo', 30)->default('inicial'); // inicial | seguimiento | reevaluacion (más adelante)
            $table->text('resumen')->nullable();

            $table->timestamps();

            $table->foreign('turno_id')
                ->references('id_turno')
                ->on('turnos')
                ->cascadeOnDelete();

            $table->foreign('paciente_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('kinesiologa_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
