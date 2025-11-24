<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicacion_actual', function (Blueprint $table) {
            $table->id('medicacion_id');

            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Textos “humanos”
            $table->string('farmaco', 255);
            $table->string('dosis', 50)->nullable();
            $table->string('frecuencia', 50)->nullable();

            // Estructurado para validar/filtrar (opcional)
            $table->decimal('dosis_cantidad', 8, 2)->nullable();
            $table->enum('dosis_unidad', ['mg', 'g', 'mcg', 'ml', 'gotas', 'comprimido', 'capsula', 'ui', 'puff', 'otra'])->nullable();

            $table->unsignedTinyInteger('cada_horas')->nullable();     // ej.: 8 = c/8h
            $table->unsignedTinyInteger('veces_por_dia')->nullable();  // ej.: 3 = 3/día
            $table->enum('frecuencia_unidad', ['hora', 'dia', 'semana', 'mes'])->nullable();

            $table->boolean('prn')->default(false); // “cuando sea necesario”
            $table->enum('via', ['oral', 'topica', 'transdermica', 'inhalatoria', 'intramuscular', 'intravenosa', 'subcutanea', 'otra'])->nullable();

            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('entrada_hc_id');
            $table->index(['farmaco']);
            $table->index('fecha_desde');
            $table->index('fecha_hasta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicacion_actual');
    }
};
