<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class AuditoriaService
{
    /**
     * Campos que nunca deben persistirse dentro de la bitácora.
     *
     * @var array<int, string>
     */
    private const CAMPOS_SENSIBLES = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'session',
        'session_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function registrar(
        string $evento,
        string $modulo,
        ?Model $auditable = null,
        array $valoresAnteriores = [],
        array $valoresNuevos = [],
        array $metadata = [],
        ?User $actor = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Auditoria {
        $actor ??= $this->actorAutenticado();

        if ($ipAddress === null || $userAgent === null) {
            $request = app()->bound('request')
                ? request()
                : null;

            $ipAddress ??= $request?->ip();
            $userAgent ??= $request?->userAgent();
        }

        $valoresAnteriores = $this->sanitizar($valoresAnteriores);
        $valoresNuevos = $this->sanitizar($valoresNuevos);
        $metadata = $this->sanitizar($metadata);

        return Auditoria::create([
            'actor_user_id' => $actor?->getKey(),
            'actor_nombre' => $this->nombreActor($actor),
            'actor_email' => $actor?->email,
            'evento' => $evento,
            'modulo' => $modulo,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'valores_anteriores' => $valoresAnteriores !== []
                ? $valoresAnteriores
                : null,
            'valores_nuevos' => $valoresNuevos !== []
                ? $valoresNuevos
                : null,
            'metadata' => $metadata !== []
                ? $metadata
                : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    private function actorAutenticado(): ?User
    {
        $actor = Auth::user();

        return $actor instanceof User
            ? $actor
            : null;
    }

    private function nombreActor(?User $actor): ?string
    {
        if ($actor === null) {
            return null;
        }

        $nombreCompleto = trim(
            $actor->name . ' ' . ($actor->apellido ?? '')
        );

        return $nombreCompleto !== ''
            ? $nombreCompleto
            : null;
    }

    /**
     * @param  array<string|int, mixed>  $datos
     * @return array<string|int, mixed>
     */
    private function sanitizar(array $datos): array
    {
        $resultado = [];

        foreach ($datos as $clave => $valor) {
            if (
                is_string($clave)
                && in_array(
                    strtolower($clave),
                    self::CAMPOS_SENSIBLES,
                    true
                )
            ) {
                continue;
            }

            $resultado[$clave] = is_array($valor)
                ? $this->sanitizar($valor)
                : $valor;
        }

        return $resultado;
    }
}
