<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_datos_clinicos_base', function (Blueprint $table) {
            // Una versión posterior no representa otra apertura de la historia.
            $table->uuid('clave_apertura')->nullable()->change();
            $table->decimal('altura_cm', 5, 2)->nullable()->change();
            $table->unique(['id', 'historia_clinica_id'], 'version_base_id_historia_uk');
        });
        Schema::table('historias_clinicas', fn (Blueprint $table) => $table->unique(['id', 'paciente_id'], 'historia_id_paciente_uk'));
        Schema::table('declaraciones_salud_paciente', fn (Blueprint $table) => $table->unique(['id', 'paciente_id'], 'declaracion_id_paciente_uk'));
    }

    public function down(): void
    {
        // Conservar precisión y claves null de versiones clínicas ya registradas.
        // Revertir la ampliación de tipos sería una conversión destructiva.
        Schema::table('declaraciones_salud_paciente', fn (Blueprint $table) => $table->dropUnique('declaracion_id_paciente_uk'));
        Schema::table('historias_clinicas', fn (Blueprint $table) => $table->dropUnique('historia_id_paciente_uk'));
        Schema::table('versiones_datos_clinicos_base', fn (Blueprint $table) => $table->dropUnique('version_base_id_historia_uk'));
    }
};
