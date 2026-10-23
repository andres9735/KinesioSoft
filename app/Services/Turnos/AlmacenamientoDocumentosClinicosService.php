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
        // Los callers planos existentes conservan descripción NULL; los pares V2 viajan juntos.
        $adjuntos = array_map(static fn (mixed $adjunto): array => is_array($adjunto)
            ? $adjunto
            : ['archivo' => $adjunto, 'descripcion' => null], $archivos);
        $errores = $this->politica->validar(array_map(
            static fn (array $adjunto): mixed => $adjunto['archivo'] ?? null,
            $adjuntos,
        ));

        if ($errores['coleccion'] !== [] || $errores['archivos'] !== []) {
            throw new AlmacenamientoDocumentalException('Los adjuntos incumplen la política documental.', violacionesPolitica: $errores);
        }

        return OperacionAlmacenamientoDocumental::almacenarValidados(
            $adjuntos,
            Storage::disk(config('documentos_clinicos.disk')),
            $this->coordinador,
            config('documentos_clinicos.directorio'),
        );
    }
}
