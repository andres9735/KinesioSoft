<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosticos_clinicos', function (Blueprint $table): void {
            $table->id();
            $table->string('sistema_origen', 20);
            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['sistema_origen', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos_clinicos');
    }
};
