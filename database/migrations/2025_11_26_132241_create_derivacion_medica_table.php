<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('derivacion_medica', function (Blueprint $table) {
            // PK con nombre explícito (como en estudio_imagen)
            $table->id('id_derivacion');

            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Datos principales
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');

            $table->enum('estado', ['emitida', 'vigente', 'vencida', 'anulada'])
                ->default('emitida');

            $table->string('medico_nombre', 100);
            $table->string('medico_matricula', 30);
            $table->string('medico_especialidad', 50);
            $table->string('institucion', 150);

            $table->text('diagnostico_texto');
            $table->text('indicaciones')->nullable();

            $table->unsignedInteger('sesiones_autorizadas');

            // Archivo: igual que estudio_imagen (upload o URL)
            $table->string('archivo_path')->nullable();    // p.ej. pacientes/12/derivaciones/2025-11-25/abcd.pdf
            $table->string('archivo_disk')->default('public');
            $table->text('archivo_url')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index('entrada_hc_id');
            $table->index('fecha_emision');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derivacion_medica');
    }
};
