<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento', function (Blueprint $table) {
            $table->id('id_movimiento');                    // PK personalizada
            $table->unsignedBigInteger('id_zona_anatomica'); // FK -> zona_anatomica
            $table->string('nombre', 80);
            $table->string('slug', 80);
            $table->string('codigo', 50)->nullable();
            $table->enum('plano', ['sagital','frontal','transversal'])->nullable();
            $table->enum('tipo_movimiento', ['activa','pasiva','activa_asistida'])->nullable();
            $table->smallInteger('rango_norm_min')->nullable();
            $table->smallInteger('rango_norm_max')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Unicidad dentro de la misma zona
            $table->unique(['id_zona_anatomica', 'nombre'], 'u_mov_zona_nombre');

            $table->foreign('id_zona_anatomica')
                ->references('id_zona_anatomica')->on('zona_anatomica')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento');
    }
};

