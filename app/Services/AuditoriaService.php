<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

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
        Model $auditable,
        array $valoresAnteriores = [],
        array $valoresNuevos = [],
        ?User $actor = null,
        ?string $url = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Auditoria {
        if (! $auditable->exists || $auditable->getKey() === null) {
            throw new InvalidArgumentException(
                'El objetivo de la auditoría debe estar persistido.'
            );
        }

        $actor ??= $this->actorAutenticado();

        if (
            $url === null
            || $ipAddress === null
            || $userAgent === null
        ) {
            $request = app()->bound('request')
                ? request()
                : null;

            $url ??= $request?->url();
            $ipAddress ??= $request?->ip();
            $userAgent ??= $request?->userAgent();
        }

        $valoresAnteriores = $this->sanitizar(
            $valoresAnteriores
        );

        $valoresNuevos = $this->sanitizar(
            $valoresNuevos
        );

        return Auditoria::create([
            'actor_user_id' => $actor?->getKey(),
            'actor_nombre_snapshot' =>
            $this->nombreActor($actor),
            'actor_email_snapshot' => $actor?->email,
            'evento' => $evento,
            'modulo' => $modulo,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'valores_anteriores' =>
            $valoresAnteriores !== []
                ? $valoresAnteriores
                : null,
            'valores_nuevos' =>
            $valoresNuevos !== []
                ? $valoresNuevos
                : null,
            'url' => $url,
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
