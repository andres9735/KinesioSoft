<?php

namespace App\Services\HistoriaClinica;

use App\Data\HistoriaClinica\ResultadoProfesionalRevision;
use App\Models\Paciente;
use Illuminate\Support\Arr;

final class ResolverIntegrantesRevision
{
    /** Misma materialización para vista previa y guardado; nunca infiere un contacto. */
    public function resolver(ResultadoProfesionalRevision $resultado, Paciente $paciente): array
    {
        $familia = $resultado->datosClinicos['familia'];
        $integrantes = isset($resultado->linaje['complemento_profesional']['familia']) ? array_map(fn ($i) => [
            ...$i, 'procedencia' => $resultado->datosClinicos['procedencia_relevamiento'], 'contacto_emergencia_confirmado' => false,
        ], $familia['integrantes']) : ($resultado->linaje['integrantes_base'] ?? []);
        if ($familia['incluir_contacto'] ?? false) {
            $integrantes[] = ['nombre_apellido' => $paciente->contacto_emergencia_nombre, 'vinculo' => $familia['vinculo_contacto'],
                'procedencia' => 'CONTACTO_EMERGENCIA_CONFIRMADO', 'contacto_emergencia_confirmado' => true];
        }

        return array_map(fn ($i) => Arr::only($i, ['nombre_apellido', 'vinculo', 'procedencia', 'contacto_emergencia_confirmado']), $integrantes);
    }
}
