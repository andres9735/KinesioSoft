<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->foreignId('titulo_profesional_id')
                ->nullable()
                ->after('user_id')
                ->constrained('titulos_profesionales')
                ->restrictOnDelete();
        });

        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->dropUnique('kinesiologas_nro_matricula_profesional_unique');
            $table->dropColumn([
                'titulo_profesional',
                'nro_matricula_profesional',
                'especialidad',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->string('titulo_profesional')->after('user_id');
            $table->string('nro_matricula_profesional')->unique()->after('titulo_profesional');
            $table->string('especialidad')->after('nro_matricula_profesional');
        });

        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('titulo_profesional_id');
        });
    }
};
