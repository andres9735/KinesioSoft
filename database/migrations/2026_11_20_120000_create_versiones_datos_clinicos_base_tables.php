<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_datos_clinicos_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_clinica_id')->constrained('historias_clinicas')->restrictOnDelete();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades_kinesiologia')->restrictOnDelete();
            $table->foreignId('turno_origen_id')->constrained('turnos')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->uuid('clave_apertura')->unique();
            $table->char('huella_solicitud', 64);
            $table->string('autor_nombre_snapshot', 511);
            $table->string('especialidad_nombre_snapshot');
            $table->string('origen', 50);
            $table->string('procedencia_relevamiento', 50);
            // Mapa finito de resultados. Los antecedentes repetibles son filas, no JSON.
            $table->json('relevamientos');
            $table->string('resultado_nucleo_familiar', 40);
            $table->text('motivo_nucleo_familiar')->nullable();
            $table->text('objetivo_salud')->nullable();
            $table->decimal('peso_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('altura_cm')->nullable();
            $table->string('ocupacion')->nullable();
            $table->string('nivel_actividad_fisica', 20)->nullable();
            $table->text('detalle_actividad_fisica')->nullable();
            $table->text('anamnesis_fisica')->nullable();
            $table->text('motivo_actualizacion')->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['historia_clinica_id', 'numero_version'], 'version_base_historia_numero_uk');
        });
        Schema::create('items_datos_clinicos_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_datos_clinicos_base_id');
            $table->foreign('version_datos_clinicos_base_id', 'items_base_version_fk')
                ->references('id')->on('versiones_datos_clinicos_base')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->text('descripcion');
            $table->string('procedencia', 50);
            $table->string('estado_codificacion', 40);
        });
        Schema::create('integrantes_nucleo_familiar_hc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_datos_clinicos_base_id');
            $table->foreign('version_datos_clinicos_base_id', 'integrantes_hc_version_fk')
                ->references('id')->on('versiones_datos_clinicos_base')->restrictOnDelete();
            $table->string('nombre_apellido')->nullable();
            $table->string('vinculo', 100);
            $table->string('procedencia', 50);
            $table->boolean('contacto_emergencia_confirmado')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrantes_nucleo_familiar_hc');
        Schema::dropIfExists('items_datos_clinicos_base');
        Schema::dropIfExists('versiones_datos_clinicos_base');
    }
};
