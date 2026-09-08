<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_declaracion_salud', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('declaracion_salud_id')
                ->constrained('declaraciones_salud_paciente')
                ->restrictOnDelete();

            $table->string('tipo', 20);

            $table->text('descripcion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_declaracion_salud');
    }
};
