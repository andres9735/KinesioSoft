<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sin backfill: un hash histórico no permite reconstruir su credencial.
        Schema::create('sobres_entrega_enlace', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('enlace_id')->unique()->constrained('enlaces_asistencia_turno')->restrictOnDelete()->restrictOnUpdate();
            $table->longText('material_cifrado')->nullable();
            $table->string('estado', 20);
            $table->string('resultado', 30);
            $table->char('reclamo_hash', 64)->nullable();
            $table->dateTime('preparado_at', 6);
            $table->dateTime('actualizado_at', 6);
            $table->dateTime('vence_at', 6)->index();
            $table->dateTime('cerrado_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sobres_entrega_enlace');
    }
};
