<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('excepciones_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('users');
            $table->date('fecha');
            $table->boolean('bloqueado')->default(true);
            $table->time('hora_desde')->nullable();
            $table->time('hora_hasta')->nullable();
            $table->string('motivo', 150)->nullable();
            $table->timestamps();

            $table->unique(['profesional_id', 'fecha', 'hora_desde', 'hora_hasta'], 'uq_prof_fecha_tramo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones_disponibilidad');
    }
};
