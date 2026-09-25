<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tecnicas_terapeuticas', function (Blueprint $table): void {
            $table->id();
            $table->string('sistema_origen', 20);
            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo');
            $table->timestamps();

            $table->unique(['sistema_origen', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tecnicas_terapeuticas');
    }
};
