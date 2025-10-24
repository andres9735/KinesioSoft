<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluacion_funcional', function (Blueprint $table) {
            // PK BIGINT UNSIGNED para ser compatible con evaluacion_rom
            $table->id('id_eval_func');

            // Aún no existe 'consulta' → dejamos la columna nullable y SIN FK
            $table->unsignedBigInteger('id_consulta')->nullable()->index();

            $table->date('fecha')->nullable();
            $table->tinyInteger('eva_dolor')->nullable();          // 0–10
            $table->string('resumen_postural', 500)->nullable();
            $table->text('texto')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_funcional');
    }
};
