<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas_profesionales_kinesiologia', function (Blueprint $table) {
            $table->dropUnique('uq_matricula_jurisdiccion_colegio');
            $table->unique(
                ['kinesiologa_id', 'jurisdiccion_id'],
                'uq_kinesiologa_jurisdiccion'
            );
        });
    }

    public function down(): void
    {
        Schema::table('matriculas_profesionales_kinesiologia', function (Blueprint $table) {
            $table->dropUnique('uq_kinesiologa_jurisdiccion');
            $table->unique(
                ['nro_matricula_profesional', 'jurisdiccion_id', 'colegio_emisor_id'],
                'uq_matricula_jurisdiccion_colegio'
            );
        });
    }
};
