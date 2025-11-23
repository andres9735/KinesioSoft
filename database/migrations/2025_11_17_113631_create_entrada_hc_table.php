<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entrada_hc', function (Blueprint $table) {
            $table->id('entrada_hc_id');

            // CORRECCIÓN: Usamos restrictOnDelete.
            // Esto impide borrar al Paciente si tiene entradas de historia clínica.
            $table->foreignId('paciente_id')
                ->constrained('pacientes', 'paciente_id')
                ->restrictOnDelete();

            // Fechas y Responsable (Obligatorios como pediste antes)
            $table->date('fecha')->useCurrent();
            $table->timestamp('fecha_creacion')->useCurrent();

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete(); // También protegemos al usuario creador

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrada_hc');
    }
};
