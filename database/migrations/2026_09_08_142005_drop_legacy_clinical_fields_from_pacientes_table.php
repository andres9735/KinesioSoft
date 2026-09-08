<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retira de PACIENTES los campos clínicos legado una vez
     * verificada su migración a HISTORIAS_CLINICAS.
     */
    public function up(): void
    {
        /*
         * OBSERVACIONES no posee un destino semánticamente equivalente
         * en el Modelo Relacional definitivo. No debe descartarse si
         * existiera información real.
         */
        $observacionesConContenido = DB::table('pacientes')
            ->whereNotNull('observaciones')
            ->whereRaw("TRIM(observaciones) <> ''")
            ->count();

        if ($observacionesConContenido > 0) {
            throw new \RuntimeException(
                'No pueden eliminarse las observaciones legado de '
                    . 'PACIENTES porque existen registros con contenido.'
            );
        }

        /*
         * Cada número de HC legado debe existir ya en
         * HISTORIAS_CLINICAS y coincidir exactamente.
         */
        $historiasNoMigradas = DB::table('pacientes as p')
            ->leftJoin(
                'historias_clinicas as hc',
                'hc.paciente_id',
                '=',
                'p.id'
            )
            ->where(function ($query): void {
                $query
                    ->whereNull('hc.id')
                    ->orWhereColumn(
                        'p.nro_historia_clinica',
                        '!=',
                        'hc.nro_historia_clinica'
                    );
            })
            ->count();

        if ($historiasNoMigradas > 0) {
            throw new \RuntimeException(
                'No pueden eliminarse los números de Historia Clínica '
                    . 'legado porque la migración no está completa.'
            );
        }

        /*
         * SQLite no permite eliminar una columna mientras exista un índice
         * que la referencie. Retiramos primero la restricción UNIQUE legado.
        */
        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropUnique(
                'pacientes_nro_historia_clinica_unique'
            );
        });

        Schema::table('pacientes', function (Blueprint $table): void {
            $table->dropColumn([
                'nro_historia_clinica',
                'observaciones',
            ]);
        });
    }

    public function down(): void
    {
        /*
         * El esquema legado exigía HC para todos los pacientes.
         * Si ya existen pacientes creados bajo el nuevo modelo sin HC,
         * no es posible reconstruir ese esquema sin inventar datos.
         */
        $pacientesSinHistoria = DB::table('pacientes as p')
            ->leftJoin(
                'historias_clinicas as hc',
                'hc.paciente_id',
                '=',
                'p.id'
            )
            ->whereNull('hc.id')
            ->count();

        if ($pacientesSinHistoria > 0) {
            throw new \RuntimeException(
                'No puede restaurarse el esquema legado de PACIENTES '
                    . 'porque existen pacientes sin Historia Clínica.'
            );
        }

        Schema::table('pacientes', function (Blueprint $table): void {
            $table->string('nro_historia_clinica')->nullable();
            $table->text('observaciones')->nullable();
        });

        $historias = DB::table('historias_clinicas')
            ->select([
                'paciente_id',
                'nro_historia_clinica',
            ])
            ->get();

        foreach ($historias as $historia) {
            DB::table('pacientes')
                ->where('id', $historia->paciente_id)
                ->update([
                    'nro_historia_clinica' =>
                    $historia->nro_historia_clinica,
                ]);
        }

        Schema::table('pacientes', function (Blueprint $table): void {
            $table->string('nro_historia_clinica')
                ->nullable(false)
                ->change();

            $table->unique('nro_historia_clinica');
        });
    }
};
