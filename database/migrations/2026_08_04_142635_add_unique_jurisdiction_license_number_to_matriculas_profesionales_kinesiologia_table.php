<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la unicidad del número de matrícula
     * dentro de cada jurisdicción.
     */
    public function up(): void
    {
        $existenDuplicados = DB::table(
            'matriculas_profesionales_kinesiologia'
        )
            ->select([
                'jurisdiccion_id',
                'nro_matricula_profesional',
            ])
            ->groupBy([
                'jurisdiccion_id',
                'nro_matricula_profesional',
            ])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($existenDuplicados) {
            throw new \RuntimeException(
                'No se puede agregar la restricción única porque existen números de matrícula duplicados dentro de una misma jurisdicción.'
            );
        }

        Schema::table(
            'matriculas_profesionales_kinesiologia',
            function (Blueprint $table) {
                $table->unique(
                    [
                        'jurisdiccion_id',
                        'nro_matricula_profesional',
                    ],
                    'uq_jurisdiccion_nro_matricula'
                );
            }
        );
    }

    /**
     * Elimina la restricción territorial del número.
     */
    public function down(): void
    {
        Schema::table(
            'matriculas_profesionales_kinesiologia',
            function (Blueprint $table) {
                $table->dropUnique(
                    'uq_jurisdiccion_nro_matricula'
                );
            }
        );
    }
};
