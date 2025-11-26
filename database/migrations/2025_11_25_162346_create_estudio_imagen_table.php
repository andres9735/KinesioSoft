<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('estudio_imagen', function (Blueprint $table) {
            $table->id('estudio_img_id');

            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('tipo', 150);
            $table->date('fecha');

            // Subida local
            $table->string('archivo_path')->nullable();   // p.ej. pacientes/12/estudios/2025-11-24/abcd.pdf
            $table->string('archivo_disk')->default('public');

            // o bien un enlace externo
            $table->text('archivo_url')->nullable();

            $table->text('informe')->nullable();

            $table->timestamps();

            $table->index('entrada_hc_id');
            $table->index('fecha');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudio_imagen');
    }
};
