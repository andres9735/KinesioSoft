<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tecnica', function (Blueprint $table) {
            $table->id('id_tecnica');

            // FK al tipo de técnica
            $table->unsignedBigInteger('id_tecnica_tipo');
            $table->foreign('id_tecnica_tipo')
                ->references('id_tecnica_tipo')
                ->on('tecnica_tipo')
                ->restrictOnDelete()   // o ->cascadeOnDelete() si preferís
                ->cascadeOnUpdate();

            // Datos
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'id_tecnica_tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tecnica');
    }
};
