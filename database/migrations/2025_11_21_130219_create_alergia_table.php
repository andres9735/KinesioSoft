<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alergia', function (Blueprint $table) {
            $table->id('alergia_id');

            // FK a entrada_hc
            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Campos propios
            $table->string('sustancia', 100);  // p.ej.: Ibuprofeno, Látex, Polen
            $table->string('reaccion', 100);   // p.ej.: urticaria, disnea, anafilaxia
            $table->enum('gravedad', ['leve', 'moderada', 'severa', 'anafilaxia', 'desconocida'])
                ->default('desconocida');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('entrada_hc_id');
            $table->index('sustancia');
            $table->index('gravedad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alergia');
    }
};
