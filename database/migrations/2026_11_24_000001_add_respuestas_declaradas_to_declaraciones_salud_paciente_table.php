<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CAMPOS = ['antecedentes_respuesta', 'cirugias_respuesta', 'alergias_respuesta', 'medicacion_respuesta'];

    public function up(): void
    {
        $db = DB::connection();
        if (! in_array($db->getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new LogicException('Motor no compatible con las respuestas declaradas.');
        }
        $grammar = $db->getQueryGrammar();
        $tabla = $grammar->wrapTable('declaraciones_salud_paciente');
        $adiciones = [];
        foreach (self::CAMPOS as $campo) {
            $columna = $grammar->wrap($campo);
            // HEX compara los bytes canónicos sin depender de colación, case o espacios.
            // El tercer carácter evita aceptar 'si ' al truncar espacios sobrantes en MariaDB.
            // CHECK sigue limitando el dominio a dos bytes, también sin modo SQL estricto.
            // NULL conserva la ausencia de evidencia histórica, sin reconstruir respuestas.
            $adiciones[] = "ADD COLUMN {$columna} VARCHAR(3) NULL CHECK ({$columna} IS NULL OR HEX({$columna}) IN ('7369', '6E6F'))";
        }
        if ($db->getDriverName() === 'sqlite') {
            $db->transaction(function () use ($db, $tabla, $adiciones) {
                foreach ($adiciones as $adicion) {
                    $db->statement("ALTER TABLE {$tabla} {$adicion}");
                }
            });
        } else {
            // Una sola sentencia evita dejar solo algunas respuestas agregadas.
            $db->statement("ALTER TABLE {$tabla} ".implode(', ', $adiciones));
        }
    }

    public function down(): void
    {
        Schema::table('declaraciones_salud_paciente', function ($table) {
            $table->dropColumn(self::CAMPOS);
        });
    }
};
