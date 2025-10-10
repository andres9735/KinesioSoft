<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categoria_ejercicio', function (Blueprint $table) {
            $table->id('id_categoria_ejercicio');
            $table->enum('tipo', [
                'movilidad',
                'fuerza',
                'estiramiento',
                'propiocepcion',
                'cardiorrespiratorio',
                'funcional'
            ])->default('fuerza');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_ejercicio');
    }
};
