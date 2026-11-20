<?php

namespace App\Services\HistoriaClinica;

final class ResolverRecorridoHistoriaClinica
{
    /**
     * Cuarta decisión UC-14 A/C. null significa que no hay evidencia suficiente
     * sobre la disponibilidad de los datos de base; nunca equivale a disponible.
     * Estos datos son internos, no respuestas aceptadas del navegador.
     */
    public function resolver(
        ?bool $historiaActiva,
        bool $declaracionPendiente,
        bool $declaracionRevisada,
        ?bool $datosBaseDisponibles = null,
    ): string {
        if ($historiaActiva === false) {
            return 'historia_inactiva';
        }

        if ($historiaActiva === null) {
            if ($declaracionRevisada) {
                return 'declaracion_revisada_sin_historia';
            }

            return $declaracionPendiente ? 'revision_c' : 'apertura_a';
        }

        return match ($datosBaseDisponibles) {
            true => 'expediente_disponible',
            false => 'completar_mismo_expediente',
            null => 'verificar_datos_base',
        };
    }
}
