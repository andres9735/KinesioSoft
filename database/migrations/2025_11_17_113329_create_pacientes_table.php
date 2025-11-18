<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id('paciente_id');
            // user_id opcional (permite pacientes sin cuenta). Único cuando no es null
            $table->foreignId('user_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            // Datos clínicos/base del paciente (ajusta a tu gusto)
            $table->string('nombre');
            $table->string('dni')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // 1:1 con users cuando existe (MySQL permite múltiples NULLs en único)
            $table->unique(['user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('pacientes');
    }
};

