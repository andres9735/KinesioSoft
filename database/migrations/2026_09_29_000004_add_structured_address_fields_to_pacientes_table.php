<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->foreignId('localidad_id')->nullable();
            $table->string('calle', 150)->nullable();
            $table->string('altura_domicilio', 30)->nullable();
            $table->string('piso_departamento', 50)->nullable();
            $table->string('referencia_domicilio', 500)->nullable();

            $table->index('localidad_id');

            $table->foreign('localidad_id')
                ->references('id')
                ->on('localidades')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropForeign(['localidad_id']);
        });

        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropIndex(['localidad_id']);
        });

        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropColumn([
                'localidad_id',
                'calle',
                'altura_domicilio',
                'piso_departamento',
                'referencia_domicilio',
            ]);
        });
    }
};
