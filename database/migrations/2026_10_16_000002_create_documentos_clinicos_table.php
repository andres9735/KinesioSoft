<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_clinicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paciente_id');
            $table->foreignId('cargado_por_user_id');
            $table->string('origen', 30);
            $table->string('estado_validacion', 30);
            $table->string('tipo_documento_clinico', 30);
            $table->string('nombre_original', 255);
            $table->string('ruta_storage', 255)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamanio_bytes');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('paciente_id')
                ->references('id')
                ->on('pacientes')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('cargado_por_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_clinicos');
    }
};
