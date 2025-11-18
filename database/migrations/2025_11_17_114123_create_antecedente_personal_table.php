<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('antecedente_personal', function (Blueprint $table) {
            $table->id('antecedente_personal_id');

            // CORRECCIÓN 1: Referencia explícita a 'entrada_hc_id'
            $table->foreignId('entrada_hc_id')
                ->constrained('entrada_hc', 'entrada_hc_id')
                ->cascadeOnDelete();

            // CORRECCIÓN 2: Referencia explícita a 'antecedente_personal_tipo_id'
            // Nota: Tu columna local es 'tipo_id', la foránea es 'antecedente_personal_tipo_id'
            $table->foreignId('tipo_id')
                ->constrained('antecedente_personal_tipo', 'antecedente_personal_tipo_id')
                ->restrictOnDelete();

            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecedente_personal');
    }
};
