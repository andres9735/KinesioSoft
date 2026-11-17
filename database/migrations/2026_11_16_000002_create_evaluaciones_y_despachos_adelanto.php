<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_adelanto', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('intencion_evaluacion_cupo_id')->unique()->constrained('intenciones_evaluacion_cupo')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('oferta_id')->nullable()->unique()->constrained('ofertas_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->string('resultado', 32);
            $table->dateTime('procesada_at', 6);
        });
        Schema::create('despachos_oferta_adelanto', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('oferta_id')->unique()->constrained('ofertas_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete()->restrictOnUpdate();
            $table->char('token_hash', 64)->unique();
            $table->longText('material_cifrado')->nullable();
            $table->string('estado', 24)->index();
            $table->dateTime('preparado_at', 6);
            $table->dateTime('vence_at', 6);
            $table->dateTime('reclamado_at', 6)->nullable();
            $table->dateTime('resuelto_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despachos_oferta_adelanto');
        Schema::dropIfExists('evaluaciones_adelanto');
    }
};
