<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paciente_padecimiento', function (Blueprint $table) {
            $table->bigIncrements('id_padecimiento');

            $table->unsignedBigInteger('paciente_id');

            // ✅ Opción A: el padecimiento puede nacer ligado a la consulta actual,
            // pero lo dejamos nullable para no atarte a una consulta siempre.
            $table->unsignedBigInteger('id_consulta')->nullable();

            // ✅ En iteración: dejar nullable hasta tener UI/catálogo sólido
            $table->unsignedBigInteger('id_padecimiento_tipo');
            $table->unsignedBigInteger('id_zona_anatomica')->nullable();

            // ✅ Para crear "borrador" sin fricción
            $table->string('nombre', 120);

            // ✅ Puede ser nullable si preferís, pero con default te simplifica PASO 4
            $table->date('fecha_inicio');

            // VARCHAR (ENUM lógico)
            $table->string('lateralidad', 30)->nullable();  // der|izq|bilateral|...
            $table->string('severidad', 30)->nullable();    // leve|moderada|severa|...
            $table->string('estado', 50)->default('en_progreso'); // en_progreso|activo|cerrado

            $table->integer('prioridad')->nullable();
            $table->string('origen', 30)->nullable(); // traumático|degenerativo|postquirúrgico|...

            $table->text('notas')->nullable();
            $table->string('detalle_zona', 100)->nullable();

            $table->dateTime('fecha_cierre')->nullable();
            $table->string('motivo_cierre', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // =========================
            // FKs
            // =========================
            $table->foreign('paciente_id')->references('paciente_id')->on('pacientes');
            $table->foreign('id_consulta')->references('id_consulta')->on('consultas');
            $table->foreign('id_padecimiento_tipo')->references('id_padecimiento_tipo')->on('padecimiento_tipo');
            $table->foreign('id_zona_anatomica')->references('id_zona_anatomica')->on('zona_anatomica');

            // =========================
            // Índices
            // =========================
            $table->index(['paciente_id', 'estado']);
            $table->index(['id_consulta']);
            $table->index(['paciente_id', 'id_consulta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_padecimiento');
    }
};
