<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migra los números de Historia Clínica legado desde PACIENTES
     * hacia HISTORIAS_CLINICAS.
     *
     * La columna legado se conserva todavía en PACIENTES.
     * Su eliminación se realizará en una migración posterior,
     * únicamente después de verificar la copia.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $pacientes = DB::table('pacientes')
                ->select([
                    'id',
                    'nro_historia_clinica',
                ])
                ->orderBy('id')
                ->get();

            foreach ($pacientes as $paciente) {
                $numeroHistoriaClinica = $paciente->nro_historia_clinica;

                if (
                    $numeroHistoriaClinica === null
                    || trim($numeroHistoriaClinica) === ''
                ) {
                    throw new \RuntimeException(
                        "El paciente {$paciente->id} no posee un número "
                            . 'de Historia Clínica legado válido.'
                    );
                }

                $historiaExistente = DB::table('historias_clinicas')
                    ->where('paciente_id', $paciente->id)
                    ->first();

                /*
                 * Si ya existe una fila compatible, la consideramos
                 * correctamente migrada. Si existe con otro número,
                 * detenemos la migración para no ocultar inconsistencias.
                 */
                if ($historiaExistente !== null) {
                    if (
                        $historiaExistente->nro_historia_clinica
                        !== $numeroHistoriaClinica
                    ) {
                        throw new \RuntimeException(
                            "Existe una Historia Clínica incompatible "
                                . "para el paciente {$paciente->id}."
                        );
                    }

                    continue;
                }

                DB::table('historias_clinicas')->insert([
                    'paciente_id' => $paciente->id,
                    'nro_historia_clinica' => $numeroHistoriaClinica,
                    'activa' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            /*
             * Verificación posterior a la copia:
             * cada paciente legado debe poseer exactamente el mismo
             * número en HISTORIAS_CLINICAS.
             */
            foreach ($pacientes as $paciente) {
                $numeroMigrado = DB::table('historias_clinicas')
                    ->where('paciente_id', $paciente->id)
                    ->value('nro_historia_clinica');

                if (
                    $numeroMigrado
                    !== $paciente->nro_historia_clinica
                ) {
                    throw new \RuntimeException(
                        "No pudo verificarse la migración de la Historia "
                            . "Clínica del paciente {$paciente->id}."
                    );
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $pacientes = DB::table('pacientes')
                ->select([
                    'id',
                    'nro_historia_clinica',
                ])
                ->get();

            foreach ($pacientes as $paciente) {
                DB::table('historias_clinicas')
                    ->where('paciente_id', $paciente->id)
                    ->where(
                        'nro_historia_clinica',
                        $paciente->nro_historia_clinica
                    )
                    ->delete();
            }
        });
    }
};
