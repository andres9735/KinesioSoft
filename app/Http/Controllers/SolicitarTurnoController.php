<?php

namespace App\Http\Controllers;

use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Http\Requests\ConfirmarPrimeraConsultaRequest;
use App\Http\Requests\ConsultarDisponibilidadPrimeraConsultaRequest;
use App\Models\Kinesiologa;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class SolicitarTurnoController extends Controller
{
    public function disponibilidad(ConsultarDisponibilidadPrimeraConsultaRequest $request, DisponibilidadPrimeraConsultaService $disponibilidad): JsonResponse
    {
        $datos = $request->validated();
        $profesional = Kinesiologa::findOrFail($datos['kinesiologa_id']);
        $desde = CarbonImmutable::createFromFormat('!Y-m-d', $datos['desde'], config('app.timezone'));
        $hasta = CarbonImmutable::createFromFormat('!Y-m-d', $datos['hasta'], config('app.timezone'));

        try {
            $slots = $disponibilidad->consultarDisponibilidad($profesional, $desde, $hasta);
        } catch (ProfesionalNoElegibleException) {
            return $this->conflicto('profesional_no_elegible', 'La profesional seleccionada ya no está disponible para atender.');
        }

        return response()->json(['slots' => array_map(static fn (array $slot): array => [
            'inicio_at' => $slot['inicio_at']->toDateTimeString(),
            'fin_at' => $slot['fin_at']->toDateTimeString(),
        ], $slots)]);
    }

    public function store(ConfirmarPrimeraConsultaRequest $request, ConfirmarPrimeraConsultaAction $confirmar): JsonResponse
    {
        $datos = $request->validated();
        $inicio = isset($datos['inicio_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $datos['inicio_at'], config('app.timezone'))
            : null;

        try {
            $resultado = $confirmar->ejecutar(
                actor: $request->user(),
                claveIdempotencia: $datos['clave_idempotencia'],
                kinesiologaId: isset($datos['kinesiologa_id']) ? (int) $datos['kinesiologa_id'] : null,
                inicioAt: $inicio,
                motivoSolicitud: $datos['motivo_solicitud'] ?? null,
                adjuntos: $datos['adjuntos'] ?? [],
            );
        } catch (PrimeraConsultaActivaException) {
            return $this->conflicto('primera_consulta_activa', 'Ya tenés una Primera consulta futura activa.');
        } catch (ProfesionalNoElegibleException) {
            return $this->conflicto('profesional_no_elegible', 'La profesional seleccionada ya no está disponible para atender.');
        } catch (SlotNoDisponibleException) {
            return $this->conflicto('slot_no_disponible', 'El horario seleccionado ya no está disponible.');
        }

        return response()->json([
            'turno_id' => $resultado->turno->getKey(),
            'es_replay' => $resultado->esReplay,
            'redirect_url' => route('turnos.mis-turnos.index', ['turno' => $resultado->turno->getKey()]),
        ], $resultado->esReplay ? 200 : 201);
    }

    private function conflicto(string $code, string $message): JsonResponse
    {
        return response()->json(compact('code', 'message'), 409);
    }
}
