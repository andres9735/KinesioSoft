<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salidas_oferta_adelanto', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('oferta_id')->unique()->constrained('ofertas_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->string('resultado', 32);
            $table->dateTime('registrada_at', 6);
            $table->string('continuacion', 32)->default('pendiente')->index();
            $table->foreignId('oferta_siguiente_id')->nullable()->constrained('ofertas_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('continuada_at', 6)->nullable();
        });
        Schema::create('intentos_despacho_adelanto', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('despacho_id')->constrained('despachos_oferta_adelanto')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('numero');
            $table->string('resultado', 24);
            $table->dateTime('iniciado_at', 6);
            $table->dateTime('resuelto_at', 6)->nullable();
            $table->unique(['despacho_id', 'numero']);
        });
        Schema::table('despachos_oferta_adelanto', function (Blueprint $table): void {
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('primer_fallo_at', 6)->nullable();
            $table->dateTime('proximo_intento_at', 6)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('despachos_oferta_adelanto', function (Blueprint $table): void {
            $table->dropColumn(['intentos', 'primer_fallo_at', 'proximo_intento_at']);
        });
        Schema::dropIfExists('intentos_despacho_adelanto');
        Schema::dropIfExists('salidas_oferta_adelanto');
    }
};
