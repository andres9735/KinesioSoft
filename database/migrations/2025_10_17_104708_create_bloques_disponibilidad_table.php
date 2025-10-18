<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloques_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('users'); // OK (users.id)
            $table->unsignedBigInteger('consultorio_id')->nullable();
            $table->foreign('consultorio_id')
                ->references('id_consultorio')
                ->on('consultorio')
                ->nullOnDelete();
            $table->tinyInteger('dia_semana'); // 0..6
            $table->time('hora_desde');
            $table->time('hora_hasta');
            $table->boolean('activo')->default(true);
            $table->smallInteger('duracion_minutos')->default(45);
            $table->timestamps();

            $table->unique(['profesional_id', 'dia_semana', 'hora_desde', 'hora_hasta'], 'uq_prof_dia_tramo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloques_disponibilidad');
    }
};
