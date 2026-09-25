<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_equipo_terapeutico', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('categoria_clinica_id')
                ->constrained('categorias_clinicas')
                ->restrictOnDelete();
            $table->boolean('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_equipo_terapeutico');
    }
};
