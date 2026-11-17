<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salidas_oferta_adelanto', function (Blueprint $table): void {
            $table->string('estado_turno_aceptado', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('salidas_oferta_adelanto', function (Blueprint $table): void {
            $table->dropColumn('estado_turno_aceptado');
        });
    }
};
