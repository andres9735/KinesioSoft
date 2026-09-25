<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recursos_ejercicio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ejercicio_id')
                ->constrained('ejercicios_domiciliarios')
                ->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('origen', 20);
            $table->string('ruta_storage')->nullable();
            $table->string('url_externa')->nullable();
            $table->string('titulo');
            $table->unsignedInteger('orden');
            $table->boolean('activo');
            $table->timestamps();

            $table->unique(['ejercicio_id', 'ruta_storage']);
            $table->unique(['ejercicio_id', 'url_externa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos_ejercicio');
    }
};
