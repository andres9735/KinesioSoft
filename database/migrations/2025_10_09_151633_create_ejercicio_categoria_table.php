<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ejercicio_categoria', function (Blueprint $table) {
            $table->id('id_ejercicio_categoria');

            $table->unsignedBigInteger('id_ejercicio');
            $table->unsignedBigInteger('id_categoria_ejercicio');

            $table->timestamps();

            $table->foreign('id_ejercicio')
                ->references('id_ejercicio')->on('ejercicio')
                ->cascadeOnDelete();

            $table->foreign('id_categoria_ejercicio')
                ->references('id_categoria_ejercicio')->on('categoria_ejercicio')
                ->cascadeOnDelete();

            $table->unique(
                ['id_ejercicio', 'id_categoria_ejercicio'],
                'ejercicio_categoria_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicio_categoria');
    }
};
