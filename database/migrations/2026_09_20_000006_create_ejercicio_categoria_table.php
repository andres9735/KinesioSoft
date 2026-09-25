<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejercicio_categoria', function (Blueprint $table): void {
            $table->foreignId('ejercicio_id')
                ->constrained('ejercicios_domiciliarios')
                ->restrictOnDelete();
            $table->foreignId('categoria_id')
                ->constrained('categorias_clinicas')
                ->restrictOnDelete();

            $table->primary(['ejercicio_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicio_categoria');
    }
};
