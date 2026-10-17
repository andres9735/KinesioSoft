<?php

namespace App\Http\Controllers;

use App\Models\DocumentoClinico;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DescargarDocumentoClinicoController extends Controller
{
    public function __invoke(Request $request, DocumentoClinico $documento, AuditoriaService $auditoria): StreamedResponse
    {
        Gate::authorize('download', $documento);
        // Laravel registra HEAD junto a GET; no abrir ni auditar ese método como descarga.
        abort_unless($request->isMethod('GET'), 405);

        $directorio = config('documentos_clinicos.directorio');
        $ruta = $documento->ruta_storage;
        abort_unless(is_string($directorio)
            && preg_match('/\A[a-z0-9_-]+(?:\/[a-z0-9_-]+)*\z/', $directorio)
            && str_starts_with($ruta, $directorio.'/')
            && Str::isUuid(substr($ruta, strlen($directorio) + 1)), 404);

        try {
            $stream = Storage::disk(config('documentos_clinicos.disk'))->readStream($ruta);
        } catch (UnableToReadFile) {
            abort(404);
        }
        abort_unless(is_resource($stream), 404);

        try {
            $response = response()->streamDownload(function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            }, null, [
                'Content-Disposition' => $this->disposition($documento->nombre_original),
                'Content-Type' => $documento->mime_type,
                'Content-Length' => (string) $documento->tamanio_bytes,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);

            $auditoria->registrar(
                evento: 'documento_clinico_descargado',
                modulo: 'Turnos',
                auditable: $documento,
                valoresNuevos: [
                    'documento_clinico_id' => $documento->getKey(),
                    'paciente_id' => $documento->paciente_id,
                    'origen' => $documento->origen,
                    'turno_id' => $documento->origenTurno()->value('turno_id'),
                ],
                actor: $request->user(),
            );

            return $response;
        } catch (Throwable $error) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw $error;
        }
    }

    private function disposition(string $nombre): string
    {
        try {
            if (trim($nombre) !== '') {
                return HeaderUtils::makeDisposition('attachment', $nombre, str_replace('%', '', Str::ascii($nombre)));
            }
        } catch (InvalidArgumentException) {
            // Symfony rechaza controles/separadores: usar un nombre neutro, sin alterar metadata.
        }

        return HeaderUtils::makeDisposition('attachment', 'documento-clinico');
    }
}
