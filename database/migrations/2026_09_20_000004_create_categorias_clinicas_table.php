<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_clinicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('categoria_padre_id')
                ->nullable()
                ->constrained('categorias_clinicas')
                ->restrictOnDelete();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->string('tipo', 20);
            $table->boolean('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_clinicas');
    }
};
