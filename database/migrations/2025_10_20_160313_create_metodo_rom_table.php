<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_rom', function (Blueprint $table) {
            // PK personalizada
            $table->id('id_metodo');

            // Campos principales
            $table->string('nombre', 80);
            $table->string('slug', 80);
            $table->string('codigo', 50)->nullable();

            // Clasificación del método / instrumento
            $table->enum('tipo', ['manual', 'digital', 'inclinometro', 'imu', 'visual'])->nullable();

            // Metadatos (opcionales)
            $table->tinyInteger('precision_decimales')->nullable();
            $table->string('unidad_defecto', 10)->default('°');
            $table->string('fabricante', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->date('fecha_calibracion')->nullable();

            // Estado
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // Restricciones útiles
            $table->unique('nombre'); // o unique(['nombre','tipo']) si preferís
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_rom');
    }
};
