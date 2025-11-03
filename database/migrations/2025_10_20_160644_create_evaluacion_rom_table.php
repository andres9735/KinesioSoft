<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_rom', function (Blueprint $table) {
            $table->id('id_eval_rom');

            // Relaciones principales
            $table->foreignId('id_eval_func')
                ->constrained('evaluacion_funcional', 'id_eval_func')
                ->cascadeOnDelete();

            $table->foreignId('id_movimiento')
                ->constrained('movimiento', 'id_movimiento');

            $table->foreignId('id_metodo')
                ->constrained('metodo_rom', 'id_metodo');

            // Datos clínicos
            $table->enum('lado', ['izq', 'der', 'bilateral'])
                ->comment('Lado del cuerpo evaluado');
            $table->smallInteger('valor_grados')->nullable()
                ->comment('Valor medido en grados');
            $table->string('observaciones', 255)->nullable();

            $table->timestamps();

            // Índices útiles
            //$table->index(['id_eval_func']);
            //$table->index(['id_movimiento']);
            //$table->index(['id_metodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_rom');
    }
};
