<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la cabecera longitudinal de Historia Clínica.
     *
     * La migración de los números históricos actualmente almacenados
     * en pacientes.nro_historia_clinica se realizará en una migración
     * posterior, antes de eliminar la columna legado.
     */
    public function up(): void
    {
        Schema::create('historias_clinicas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('paciente_id')
                ->unique()
                ->constrained('pacientes')
                ->restrictOnDelete();

            $table->string('nro_historia_clinica')
                ->unique();

            $table->boolean('activa')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historias_clinicas');
    }
};
