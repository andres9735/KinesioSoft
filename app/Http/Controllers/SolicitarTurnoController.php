<?php

namespace App\Http\Controllers;

use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Http\Requests\ConfirmarPrimeraConsultaRequest;
use App\Http\Requests\ConsultarDisponibilidadPrimeraConsultaRequest;
use App\Http\Requests\ConsultarProfesionalesDisponiblesPrimeraConsultaRequest;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\DisponibilidadAgregadaPrimeraConsultaService;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SolicitarTurnoController extends Controller
{
    public function create(Request $request, ElegibilidadPrimeraConsultaService $d01, RelojAplicacion $reloj): View
    {
        $paciente = $request->user()->paciente()->first();
        abort_unless($paciente instanceof Paciente, 403);

        if ($d01->tienePrimeraConsultaFuturaActiva($paciente)) {
            return view('turnos.solicitar.create', [
                'bloqueadoPorPrimeraConsulta' => true,
                'urlMisTurnos' => route('turnos.mis-turnos.index'),
            ]);
        }

        $politica = config('documentos_clinicos');
        $extensionesPorMime = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
        $extensiones = array_merge(...array_map(static fn (string $mime): array => $extensionesPorMime[$mime], $politica['mime_permitidos']));

        return view('turnos.solicitar.create', ['configuracionWizard' => [
            'uuid' => (string) Str::uuid(),
            'maxMotivo' => 500,
            'limites' => [
                'maxArchivos' => $politica['max_archivos'],
                'maxBytesPorArchivo' => $politica['max_bytes_por_archivo'],
                'maxBytesTotal' => $politica['max_bytes_total'],
                'mimePermitidos' => $politica['mime_permitidos'],
                'extensionesPermitidas' => $extensiones,
            ],
            'urlMisTurnos' => route('turnos.mis-turnos.index'),
            'urlDisponibilidad' => route('turnos.solicitar.disponibilidad'),
            'urlProfesionalesDisponibles' => route('turnos.solicitar.profesionales-disponibles'),
            'urlConfirmacion' => route('turnos.solicitar.store'),
            'fechaInicial' => $reloj->ahora()->toDateString(),
        ]]);
    }

    public function disponibilidad(ConsultarDisponibilidadPrimeraConsultaRequest $request, DisponibilidadAgregadaPrimeraConsultaService $disponibilidad): JsonResponse
    {
        $datos = $request->validated();
        $desde = CarbonImmutable::createFromFormat('!Y-m-d', $datos['desde'], config('app.timezone'));
        $hasta = CarbonImmutable::createFromFormat('!Y-m-d', $datos['hasta'], config('app.timezone'));
        $dias = $disponibilidad->consultarDisponibilidad($desde, $hasta);

        return response()->json([
            'desde' => $datos['desde'],
            'hasta' => $datos['hasta'],
            'dias' => array_map(static fn (array $dia): array => [
                'fecha' => $dia['fecha'],
                'horarios' => array_map(static fn (array $horario): array => [
                    'inicio_at' => $horario['inicio_at']->toDateTimeString(),
                ], $dia['horarios']),
            ], $dias),
        ]);
    }

    public function profesionalesDisponibles(ConsultarProfesionalesDisponiblesPrimeraConsultaRequest $request, DisponibilidadAgregadaPrimeraConsultaService $disponibilidad): JsonResponse
    {
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $request->validated('inicio_at'), config('app.timezone'));
        $horario = collect($disponibilidad->consultarDisponibilidad($inicio, $inicio))
            ->flatMap(static fn (array $dia): array => $dia['horarios'])
            ->first(static fn (array $horario): bool => $horario['inicio_at']->equalTo($inicio));
        $contribuciones = $horario['profesionales'] ?? [];
        $profesionales = [];

        if ($contribuciones !== []) {
            $identidades = Kinesiologa::query()->with('user:id,name,apellido')
                ->whereKey(array_column($contribuciones, 'kinesiologa_id'))
                ->get(['id', 'user_id'])->keyBy('id');

            foreach ($contribuciones as $contribucion) {
                $profesional = $identidades->get($contribucion['kinesiologa_id']);
                if ($profesional === null || $profesional->user === null) {
                    continue;
                }
                $profesionales[] = [
                    'kinesiologa_id' => $profesional->getKey(),
                    'nombre' => trim($profesional->user->name.' '.$profesional->user->apellido),
                    'fin_at' => $contribucion['fin_at']->toDateTimeString(),
                ];
            }
        }

        return response()->json(['inicio_at' => $inicio->toDateTimeString(), 'profesionales' => $profesionales]);
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
