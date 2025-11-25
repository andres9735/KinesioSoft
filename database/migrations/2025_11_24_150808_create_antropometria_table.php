<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antropometria', function (Blueprint $table) {
            $table->id('antropometria_id');

            // Pivot a la historia clínica
            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Datos
            $table->date('fecha');
            $table->decimal('altura_cm', 5, 2); // ej: 172.5
            $table->decimal('peso_kg',   5, 2); // ej:  68.10

            $table->timestamps();

            // Índices útiles
            $table->index('entrada_hc_id');
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antropometria');
    }
};
