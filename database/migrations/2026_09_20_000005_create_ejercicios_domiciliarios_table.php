<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejercicios_domiciliarios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion');
            $table->unsignedInteger('series_recomendadas')->nullable();
            $table->unsignedInteger('repeticiones_recomendadas')->nullable();
            $table->unsignedInteger('duracion_segundos_recomendada')->nullable();
            $table->unsignedInteger('frecuencia_cantidad_base')->nullable();
            $table->string('frecuencia_periodo_base', 20)->nullable();
            $table->unsignedInteger('descanso_segundos_base')->nullable();
            $table->string('carga_resistencia_sugerida')->nullable();
            $table->string('dificultad_base', 20);
            $table->text('contraindicaciones')->nullable();
            $table->text('notas_clinicas')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicios_domiciliarios');
    }
};
