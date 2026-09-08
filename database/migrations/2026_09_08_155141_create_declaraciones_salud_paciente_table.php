<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaraciones_salud_paciente', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('paciente_id')
                ->constrained('pacientes')
                ->restrictOnDelete();

            $table->text('objetivo_salud');

            $table->decimal('peso_kg', 5, 2);

            $table->decimal('altura_cm', 5, 2);

            $table->string('ocupacion');

            $table->string('nivel_actividad_fisica', 20);

            $table->text('detalle_actividad_fisica');

            $table->string('estado', 30);

            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaraciones_salud_paciente');
    }
};
