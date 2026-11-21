<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmaciones_revision_declaracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('declaracion_salud_id')->unique('confirmacion_declaracion_uk');
            $table->unsignedBigInteger('historia_clinica_id');
            $table->unsignedBigInteger('paciente_id');
            $table->unsignedBigInteger('version_base_id')->nullable();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete();
            $table->foreignId('autor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades_kinesiologia')->restrictOnDelete();
            $table->foreignId('turno_origen_id')->constrained('turnos')->restrictOnDelete();
            $table->string('autor_nombre_snapshot', 511);
            $table->string('especialidad_nombre_snapshot');
            $table->uuid('clave_confirmacion')->unique('confirmacion_clave_uk');
            $table->char('huella_declaracion', 64);
            $table->char('huella_resultado', 64);
            $table->json('linaje_resultado');
            $table->string('accion', 80);
            $table->dateTime('confirmada_at', 6);
            $table->foreign(['declaracion_salud_id', 'paciente_id'], 'confirmacion_declaracion_paciente_fk')
                ->references(['id', 'paciente_id'])->on('declaraciones_salud_paciente')->restrictOnDelete();
            $table->foreign(['historia_clinica_id', 'paciente_id'], 'confirmacion_historia_paciente_fk')
                ->references(['id', 'paciente_id'])->on('historias_clinicas')->restrictOnDelete();
            $table->foreign(['version_base_id', 'historia_clinica_id'], 'confirmacion_version_base_fk')
                ->references(['id', 'historia_clinica_id'])->on('versiones_datos_clinicos_base')->restrictOnDelete();
            $table->unique(['id', 'declaracion_salud_id', 'historia_clinica_id'], 'confirmacion_id_declaracion_historia_uk');
            $table->unique(['id', 'declaracion_salud_id', 'kinesiologa_id'], 'confirmacion_id_declaracion_autora_uk');
        });
        Schema::create('revisiones_declaracion_salud', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('confirmacion_revision_id');
            $table->unsignedBigInteger('declaracion_salud_id');
            $table->unsignedBigInteger('kinesiologa_id');
            $table->string('bloque', 40);
            $table->string('dictamen', 20);
            $table->text('observacion_profesional')->nullable();
            $table->json('datos_originales');
            $table->json('correccion_profesional')->nullable();
            $table->json('datos_admitidos');
            $table->json('procedencias');
            $table->dateTime('created_at', 6);
            $table->unique(['declaracion_salud_id', 'bloque'], 'revision_declaracion_bloque_uk');
            $table->foreign(['confirmacion_revision_id', 'declaracion_salud_id', 'kinesiologa_id'], 'revision_confirmacion_autora_fk')
                ->references(['id', 'declaracion_salud_id', 'kinesiologa_id'])->on('confirmaciones_revision_declaracion')->restrictOnDelete();
        });
        Schema::create('incorporaciones_declaracion_salud', function (Blueprint $table) {
            $table->unsignedBigInteger('version_datos_clinicos_base_id')->primary('incorporacion_version_pk');
            $table->unsignedBigInteger('declaracion_salud_id')->unique('incorporacion_declaracion_uk');
            $table->unsignedBigInteger('confirmacion_revision_id')->unique('incorporacion_confirmacion_uk');
            $table->unsignedBigInteger('historia_clinica_id');
            $table->foreign(['confirmacion_revision_id', 'declaracion_salud_id', 'historia_clinica_id'], 'incorporacion_confirmacion_fk')
                ->references(['id', 'declaracion_salud_id', 'historia_clinica_id'])->on('confirmaciones_revision_declaracion')->restrictOnDelete();
            $table->foreign(['version_datos_clinicos_base_id', 'historia_clinica_id'], 'incorporacion_version_historia_fk')
                ->references(['id', 'historia_clinica_id'])->on('versiones_datos_clinicos_base')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incorporaciones_declaracion_salud');
        Schema::dropIfExists('revisiones_declaracion_salud');
        Schema::dropIfExists('confirmaciones_revision_declaracion');
    }
};
