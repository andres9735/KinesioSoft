<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antecedente_familiar', function (Blueprint $table) {
            $table->id('antecedente_familiar_id');

            // FK a entrada_hc
            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Campos propios
            $table->string('parentesco', 50)
                ->comment('Padre, Madre, Hermano/a, Hijo/a, Abuelo/a, etc.');
                
            $table->enum('lado_familia', ['materno', 'paterno', 'ambos', 'desconocido', 'no_especifica'])
                ->default('no_especifica')
                ->comment('Lado de la familia: materno/paterno/ambos/desconocido/no_especifica');

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('entrada_hc_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecedente_familiar');
    }
};
