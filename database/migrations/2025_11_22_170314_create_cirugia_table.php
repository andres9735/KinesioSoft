<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cirugia', function (Blueprint $table) {
            $table->id('cirugia_id');

            // FK a entrada_hc
            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Campos propios
            $table->string('procedimiento', 255);
            $table->date('fecha');
            $table->enum('lateralidad', [
                'izquierda',
                'derecha',
                'bilateral',
                'no_aplica',
                'desconocida',
            ])->default('desconocida');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index('entrada_hc_id');
            $table->index('fecha');
            $table->index('lateralidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cirugia');
    }
};
