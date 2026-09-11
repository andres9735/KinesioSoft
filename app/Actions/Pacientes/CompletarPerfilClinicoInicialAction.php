<?php

namespace App\Actions\Pacientes;

use App\Models\DeclaracionSaludPaciente;
use App\Models\ItemDeclaracionSalud;
use App\Models\Paciente;
use App\Models\User;
use App\Services\AuditoriaService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CompletarPerfilClinicoInicialAction
{
    private const MAPEO_ITEMS = [
        'antecedentes' => ItemDeclaracionSalud::TIPO_ANTECEDENTE,
        'cirugias' => ItemDeclaracionSalud::TIPO_CIRUGIA,
        'alergias' => ItemDeclaracionSalud::TIPO_ALERGIA,
        'medicaciones' => ItemDeclaracionSalud::TIPO_MEDICACION,
    ];

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Completa el perfil clínico inicial del paciente y registra
     * una nueva declaración de salud pendiente de revisión.
     *
     * @param  array<string, mixed>  $data
     */
    public function ejecutar(
        User $user,
        array $data
    ): DeclaracionSaludPaciente {
        return DB::transaction(function () use (
            $user,
            $data
        ): DeclaracionSaludPaciente {
            $usuario = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $paciente = Paciente::query()
                ->where('user_id', $usuario->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->asegurarQueNoExistaDeclaracionPendiente(
                $paciente
            );

            $usuario->update([
                'dni' => $data['dni'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'sexo' => $data['sexo'],
                'telefono' => $data['telefono'],
            ]);

            $paciente->update([
                'direccion' => $data['direccion'],
                'contacto_emergencia_nombre' =>
                $data['contacto_emergencia_nombre'],
                'contacto_emergencia_telefono' =>
                $data['contacto_emergencia_telefono'],
            ]);

            $declaracion = DeclaracionSaludPaciente::create([
                'paciente_id' => $paciente->getKey(),
                'objetivo_salud' => $data['objetivo_salud'],
                'peso_kg' => $data['peso_kg'],
                'altura_cm' => $data['altura_cm'],
                'ocupacion' => $data['ocupacion'],
                'nivel_actividad_fisica' =>
                $data['nivel_actividad_fisica'],
                'detalle_actividad_fisica' =>
                $data['detalle_actividad_fisica'],
                'estado' =>
                DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
            ]);

            $items = $this->construirItems($data);

            if ($items !== []) {
                $declaracion->items()->createMany($items);
            }

            $this->auditoriaService->registrar(
                evento: 'perfil_clinico_inicial_completado',
                modulo: 'Perfiles',
                auditable: $declaracion,
                valoresNuevos: [
                    'estado' =>
                    DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
                ],
                actor: $usuario
            );

            return $declaracion->load('items');
        });
    }

    private function asegurarQueNoExistaDeclaracionPendiente(
        Paciente $paciente
    ): void {
        $existePendiente = DeclaracionSaludPaciente::query()
            ->where('paciente_id', $paciente->getKey())
            ->where(
                'estado',
                DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION
            )
            ->exists();

        if ($existePendiente) {
            throw new DomainException(
                'Ya existe una declaración de salud pendiente de revisión profesional.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{tipo: string, descripcion: string}>
     */
    private function construirItems(array $data): array
    {
        $items = [];

        foreach (self::MAPEO_ITEMS as $campo => $tipo) {
            foreach ($data[$campo] ?? [] as $descripcion) {
                $items[] = [
                    'tipo' => $tipo,
                    'descripcion' => $descripcion,
                ];
            }
        }

        return $items;
    }
}
