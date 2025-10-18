<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('equipo_terapeutico', function (Blueprint $table) {
            $table->id('id_equipo_terap');
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('marca_modelo', 150)->nullable();
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['operativo', 'baja'])->default('operativo');

            $table->unsignedBigInteger('id_consultorio');
            $table->foreign('id_consultorio')
                ->references('id_consultorio')
                ->on('consultorio')
                ->cascadeOnDelete();

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_terapeutico');
    }
};
