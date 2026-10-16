<?php

namespace App\Services\Turnos;

use App\Exceptions\Turnos\AlmacenamientoDocumentalException;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\Documentos\OperacionAlmacenamientoDocumental;
use Illuminate\Support\Facades\Storage;

final class AlmacenamientoDocumentosClinicosService
{
    public function __construct(
        private readonly PoliticaDocumentosClinicosService $politica,
        private readonly CoordinadorBloqueoDocumental $coordinador,
    ) {}

    /** @param array<array-key, mixed> $archivos */
    public function almacenar(array $archivos): OperacionAlmacenamientoDocumental
    {
        $errores = $this->politica->validar($archivos);

        if ($errores['coleccion'] !== [] || $errores['archivos'] !== []) {
            throw new AlmacenamientoDocumentalException('Los adjuntos incumplen la política documental.', violacionesPolitica: $errores);
        }

        return OperacionAlmacenamientoDocumental::almacenarValidados(
            $archivos,
            Storage::disk(config('documentos_clinicos.disk')),
            $this->coordinador,
            config('documentos_clinicos.directorio'),
        );
    }
}
