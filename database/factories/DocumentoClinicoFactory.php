<?php

namespace Database\Factories;

use App\Models\DocumentoClinico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoClinico>
 */
class DocumentoClinicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origen' => DocumentoClinico::ORIGEN_SOLICITUD_TURNO,
            'estado_validacion' => DocumentoClinico::ESTADO_VALIDACION_PENDIENTE,
            'tipo_documento_clinico' => DocumentoClinico::TIPO_SIN_CLASIFICAR,
            'nombre_original' => 'Estudio.pdf',
            'descripcion' => null,
            'ruta_storage' => 'fixtures/'.fake()->unique()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'tamanio_bytes' => 1024,
        ];
    }
}
