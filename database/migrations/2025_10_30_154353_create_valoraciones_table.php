<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('valoraciones', function (Blueprint $table) {
            $table->id();

            // FKs a users
            $table->foreignId('profesional_id')
                ->constrained('users')      // users.id
                ->restrictOnDelete();       // no borrar en cascada profesionales

            $table->foreignId('paciente_id')
                ->constrained('users')      // users.id
                ->restrictOnDelete();       // no borrar en cascada pacientes

            // 🔴 Atención: turnos usa PK "id_turno"
            $table->unsignedBigInteger('turno_id');
            $table->foreign('turno_id')
                ->references('id_turno')    // referencia correcta
                ->on('turnos')
                ->cascadeOnDelete();        // si se borra el turno, se borra la valoración

            $table->unsignedTinyInteger('puntuacion');  // 1..5
            $table->text('comentario')->nullable();

            $table->timestamps();

            // 1 valoración por turno
            $table->unique('turno_id', 'valoraciones_turno_unique');

            // Índices útiles
            $table->index('profesional_id', 'valoraciones_prof_idx');
            $table->index('paciente_id', 'valoraciones_pac_idx');
        });

        // (Opcional si usas MySQL 8+)
        // DB::statement('ALTER TABLE valoraciones ADD CONSTRAINT chk_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::table('valoraciones', function (Blueprint $table) {
            $table->dropForeign(['profesional_id']);
            $table->dropForeign(['paciente_id']);
            $table->dropForeign(['turno_id']);
        });

        Schema::dropIfExists('valoraciones');
    }
};
