<?php

namespace App\Services\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision as Fuente;
use App\Data\HistoriaClinica\ResultadoProfesionalRevision;
use App\Models\Paciente;
use App\Models\VersionDatosClinicosBase;

final class PresentarVistaPreviaRevision
{
    public function __construct(private readonly ResolverIntegrantesRevision $integrantes) {}

    /** Proyección de lectura del contrato validado. No genera versiones ni firmas. */
    public function presentar(ResultadoProfesionalRevision $resultado, ?VersionDatosClinicosBase $base, Paciente $paciente): array
    {
        $secciones = [];
        foreach (ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $titulo) {
            $antes = $base ? $this->antecedentes($base->relevamientos[$tipo], $base->items->where('tipo', $tipo)->map->getAttributes()->all()) : [];
            $bloque = $resultado->datosClinicos['bloques'][$tipo];
            $despues = $this->antecedentes($bloque['resultado'], $bloque['items']);
            $secciones[] = $this->seccion($titulo, $antes, $despues);
        }
        $antes = $base ? $this->familia($base->resultado_nucleo_familiar, $base->motivo_nucleo_familiar,
            $base->integrantes->map->getAttributes()->all()) : [];
        $familia = $resultado->datosClinicos['familia'];
        $integrantes = $this->integrantes->resolver($resultado, $paciente);
        $secciones[] = $this->seccion('Núcleo familiar', $antes, $this->familia($familia['resultado'], $familia['motivo'] ?? null, $integrantes));
        foreach (Fuente::CAMPOS as $campo) {
            $antes = $base?->$campo;
            $despues = $resultado->datosClinicos[$campo] ?? null;
            // La escala visual es estable para no presentar 170 y 170.00 como un cambio.
            if (in_array($campo, ['peso_kg', 'altura_cm'], true)) {
                $antes = $antes === null ? null : number_format((float) $antes, 2, '.', '');
                $despues = $despues === null ? null : number_format((float) $despues, 2, '.', '');
            }
            $secciones[] = $this->seccion(ConsultarRevisionDeclaracion::ETIQUETAS[$campo],
                $antes === null ? [] : [(string) $antes], $despues === null ? [] : [(string) $despues]);
        }

        if ($base?->anamnesis_fisica !== null) {
            $secciones[] = $this->seccion('Anamnesis física previa (se conserva)', [$base->anamnesis_fisica], [$base->anamnesis_fisica]);
        }

        return $secciones;
    }

    private function antecedentes(string $estado, array $items): array
    {
        return ['Relevamiento: '.(ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS[$estado] ?? $estado), ...array_map(fn ($i) => $i['descripcion'].' · '.(ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$i['procedencia']] ?? $i['procedencia']).' · '.
                match ($i['estado_codificacion']) {
                    'PENDIENTE_CODIFICACION' => 'Pendiente de codificación', default => $i['estado_codificacion']
                }, $items)];
    }

    private function familia(string $estado, ?string $motivo, array $integrantes): array
    {
        return ['Relevamiento: '.(ValidarDatosAperturaHistoriaClinica::FAMILIA[$estado] ?? $estado),
            ...($motivo ? ['Motivo: '.$motivo] : []), ...array_map(fn ($i) => ($i['nombre_apellido'] ?? 'Nombre no disponible').' · '.$i['vinculo'].
                ' · '.(ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$i['procedencia'] ?? ''] ?? ($i['procedencia'] ?? '')).
                (! empty($i['contacto_emergencia_confirmado']) ? ' · Contacto de emergencia confirmado como integrante' : ''), $integrantes)];
    }

    private function seccion(string $titulo, array $antes, array $despues): array
    {
        // Comparar ocurrencias: dos registros iguales no equivalen a uno solo.
        $restantes = array_values($despues);
        $conservados = $sustituidos = [];
        foreach ($antes as $texto) {
            $indice = array_search($texto, $restantes, true);
            if ($indice === false) {
                $sustituidos[] = $texto;
            } else {
                $conservados[] = $texto;
                unset($restantes[$indice]);
            }
        }

        return ['titulo' => $titulo, 'antes' => array_values($antes), 'despues' => array_values($despues),
            'conservado' => $conservados, 'incorporado' => array_values($restantes), 'sustituido' => $sustituidos];
    }
}
