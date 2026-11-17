<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despachos_confirmacion_adelanto', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('salida_id')->unique()->constrained('salidas_oferta_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->string('estado', 20)->default('PENDIENTE')->index();
            $table->text('material_cifrado')->nullable();
            $table->dateTime('preparado_at', 6);
            $table->dateTime('reclamado_at', 6)->nullable();
            $table->dateTime('resuelto_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despachos_confirmacion_adelanto');
    }
};
