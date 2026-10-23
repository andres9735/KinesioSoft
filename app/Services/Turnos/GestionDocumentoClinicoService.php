<?php

namespace App\Services\Turnos;

use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditoriaService;
use LogicException;

final class GestionDocumentoClinicoService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** @param array{ruta_storage: string, nombre_original: string, descripcion?: ?string, mime_type: string, tamanio_bytes: int} $metadata */
    public function registrarOrigenTurno(User $actor, Paciente $paciente, Turno $turno, array $metadata): DocumentoClinico
    {
        if ((int) $turno->paciente_id !== (int) $paciente->getKey()
            || (int) $paciente->user_id !== (int) $actor->getKey()
            || $turno->getConnection()->transactionLevel() === 0) {
            throw new LogicException('La carga documental requiere el propietario y la transacción del turno.');
        }

        $documento = DocumentoClinico::create([
            'paciente_id' => $paciente->getKey(),
            'cargado_por_user_id' => $actor->getKey(),
            'origen' => DocumentoClinico::ORIGEN_SOLICITUD_TURNO,
            'estado_validacion' => DocumentoClinico::ESTADO_VALIDACION_PENDIENTE,
            'tipo_documento_clinico' => DocumentoClinico::TIPO_SIN_CLASIFICAR,
            'nombre_original' => $metadata['nombre_original'],
            'descripcion' => $metadata['descripcion'] ?? null,
            'ruta_storage' => $metadata['ruta_storage'],
            'mime_type' => $metadata['mime_type'],
            'tamanio_bytes' => $metadata['tamanio_bytes'],
        ]);
        $documento->origenTurno()->create(['turno_id' => $turno->getKey()]);

        $this->auditoria->registrar(
            evento: 'documento_clinico_cargado',
            modulo: 'Turnos',
            auditable: $documento,
            valoresNuevos: [
                'documento_clinico_id' => $documento->getKey(),
                'turno_id' => $turno->getKey(),
                'paciente_id' => $paciente->getKey(),
                'origen' => $documento->origen,
                'estado_validacion' => $documento->estado_validacion,
                'tipo_documento_clinico' => $documento->tipo_documento_clinico,
                'mime_type' => $documento->mime_type,
                'tamanio_bytes' => $documento->tamanio_bytes,
            ],
            actor: $actor,
        );

        return $documento;
    }
}
