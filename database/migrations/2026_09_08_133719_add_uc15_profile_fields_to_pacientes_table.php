<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega al perfil administrativo del paciente los datos
     * requeridos por UC-15.
     *
     * Se mantienen temporalmente nro_historia_clinica y observaciones
     * hasta completar la migración de compatibilidad histórica.
     */
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('direccion')->nullable();
            $table->string('contacto_emergencia_nombre')->nullable();
            $table->string('contacto_emergencia_telefono', 20)->nullable();

            $table->boolean('activo')
                ->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn([
                'direccion',
                'contacto_emergencia_nombre',
                'contacto_emergencia_telefono',
                'activo',
            ]);
        });
    }
};
