<?php

namespace App\Services\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision as Fuente;
use App\Data\HistoriaClinica\ResultadoProfesionalRevision;
use App\Exceptions\HistoriaClinica\ConflictoRevisionDeclaracionException;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

final class ComprobanteVistaPreviaRevision
{
    // UC-14 C.4: el acto combina aceptación explícita, actor revalidado y registro
    // inmutable de identidad/especialidad/fecha y de la propuesta autenticada.
    public function __construct(private readonly PresentarVistaPreviaRevision $presentador) {}

    public function emitir(User $actor, array $datos, array $entrada, ResultadoProfesionalRevision $resultado): string
    {
        return Crypt::encryptString(json_encode([
            'proposito' => 'uc14-c-confirmacion-v1', 'clave' => (string) Str::uuid(), 'actor' => (int) $actor->id,
            'turno' => (int) $datos['turno']->id, 'declaracion' => $resultado->declaracionId, 'paciente' => $resultado->pacienteId,
            'profesional' => (int) $datos['turno']->kinesiologa_id, 'contexto' => $datos['contexto'],
            'entrada' => Arr::only($entrada, ['especialidad_id', 'procedencia_relevamiento', 'dictamenes', 'complemento_profesional', 'sustituciones_base']),
            'huella_resultado' => $resultado->huellaResultado, 'huella_declaracion' => $resultado->huellaDeclaracion,
            'huella_vista' => $this->huellaVista($datos, $resultado),
        ], JSON_THROW_ON_ERROR));
    }

    public function leer(string $comprobante, User $actor, int $turnoId, int $declaracionId): array
    {
        try {
            $datos = json_decode(Crypt::decryptString($comprobante), true, 64, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new ConflictoRevisionDeclaracionException('propuesta');
        }
        if (! is_array($datos) || ($datos['proposito'] ?? null) !== 'uc14-c-confirmacion-v1'
            || ($datos['actor'] ?? null) !== (int) $actor->id || ($datos['turno'] ?? null) !== $turnoId
            || ($datos['declaracion'] ?? null) !== $declaracionId || ! Str::isUuid($datos['clave'] ?? '')
            || ! is_array($datos['entrada'] ?? null) || ! is_string($datos['contexto'] ?? null)
            || ! is_int($datos['paciente'] ?? null) || ! is_int($datos['profesional'] ?? null)) {
            throw new ConflictoRevisionDeclaracionException('propuesta');
        }
        foreach (['huella_resultado', 'huella_declaracion', 'huella_vista'] as $campo) {
            if (! is_string($datos[$campo] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $datos[$campo])) {
                throw new ConflictoRevisionDeclaracionException('propuesta');
            }
        }

        return $datos;
    }

    public function huellaVista(array $datos, ResultadoProfesionalRevision $resultado): string
    {
        $especialidad = $datos['especialidades']->firstWhere('id', $resultado->datosClinicos['especialidad_id']);

        return Fuente::huellaDe(['secciones' => $this->presentador->presentar($resultado, $datos['versionBase'], $datos['paciente']),
            'turno' => Arr::only($datos['turno']->getAttributes(), ['id', 'paciente_id', 'kinesiologa_id', 'inicio_at', 'fin_at', 'tipo', 'estado']),
            'historia' => $datos['historia']?->getAttributes(), 'autor' => trim($datos['turno']->kinesiologa->user->name.' '.$datos['turno']->kinesiologa->user->apellido),
            'especialidad' => $especialidad?->nombre, 'version' => ($datos['versionBase']?->numero_version ?? 0) + 1]);
    }
}
