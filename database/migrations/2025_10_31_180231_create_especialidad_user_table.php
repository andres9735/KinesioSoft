<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('especialidad_user', function (Blueprint $table) {
            // FKs NOT NULL
            $table->foreignId('user_id')
                ->constrained('users')          // users.id
                ->cascadeOnDelete();            // si se borra el user, se limpia el pivot

            $table->foreignId('especialidad_id')
                ->constrained('especialidades') // especialidades.id
                ->cascadeOnDelete();            // si se borra la especialidad, se limpia el pivot

            // marcar una principal (regla se valida en app)
            $table->boolean('is_principal')->default(false);

            $table->timestamps();

            // Un usuario no puede repetir la misma especialidad
            $table->unique(['user_id', 'especialidad_id'], 'uq_user_especialidad');
            $table->index(['user_id', 'is_principal'], 'idx_user_principal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidad_user');
    }
};
