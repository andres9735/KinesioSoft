<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('padecimiento_diagnostico', function (Blueprint $table) {
            $table->bigIncrements('id_padecimiento_diagnostico');

            $table->unsignedBigInteger('id_padecimiento');
            $table->unsignedBigInteger('id_diagnostico_funcional');

            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->boolean('es_principal')->default(true);

            $table->text('notas')->nullable();

            $table->timestamps();

            // =========================
            // FKs
            // =========================
            $table->foreign('id_padecimiento')
                ->references('id_padecimiento')
                ->on('paciente_padecimiento');

            $table->foreign('id_diagnostico_funcional')
                ->references('id_diagnostico_funcional')
                ->on('diagnostico_funcional');

            // =========================
            // Índices / constraints útiles
            // =========================
            $table->index(['id_padecimiento', 'es_principal']);

            // Si querés mantener tu constraint anterior:
            $table->unique(
                ['id_padecimiento', 'id_diagnostico_funcional', 'vigente_desde'],
                'uq_padec_diag_vig_desde'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padecimiento_diagnostico');
    }
};
