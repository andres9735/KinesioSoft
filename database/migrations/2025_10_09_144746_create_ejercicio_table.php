<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ejercicio', function (Blueprint $table) {
            $table->id('id_ejercicio');
            $table->string('nombre', 150)->unique();
            $table->text('descripcion')->nullable();
            $table->enum('nivel_dificultad_base', ['baja', 'media', 'alta'])->default('baja');
            $table->string('url_recurso', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicio');
    }
};
