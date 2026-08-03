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
        Schema::create('matriculas_profesionales_kinesiologia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->cascadeOnDelete();
            $table->string('nro_matricula_profesional');
            $table->foreignId('jurisdiccion_id')->constrained('jurisdicciones')->restrictOnDelete();
            $table->foreignId('colegio_emisor_id')->constrained('colegios_emisores')->restrictOnDelete();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(
                ['nro_matricula_profesional', 'jurisdiccion_id', 'colegio_emisor_id'],
                'uq_matricula_jurisdiccion_colegio'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas_profesionales_kinesiologia');
    }
};
