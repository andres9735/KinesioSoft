<?php

namespace App\Services\Agenda;

use App\Models\Kinesiologa;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as Autenticacion;

final class AgendaProfesionalQueryService
{
    public function __construct(
        private readonly Autenticacion $autenticacion,
        private readonly AgendaAtencionQueryService $consulta,
    ) {}

    /** @return list<array<string, mixed>> */
    public function informacionDeJornada(int $kinesiologaId, CarbonInterface $fecha): array
    {
        return $this->informacionDePeriodo($kinesiologaId, $fecha, $fecha);
    }

    /**
     * Frontera de UC–05: identidad del guard web, rol profesional y pertenencia persistida.
     * Las precondiciones transversales de cuenta, correo y perfil se conservan para
     * la integración HTTP. No utiliza permisos de Configuración de Agenda ni de UC–06.
     * UC–29 utilizará directamente la consulta interna, sin una sesión ficticia.
     *
     * @return list<array<string, mixed>>
     */
    public function informacionDePeriodo(
        int $kinesiologaId,
        CarbonInterface $desde,
        CarbonInterface $hasta,
    ): array {
        $actor = $this->autenticacion->guard('web')->user();
        if (! $actor instanceof User || ! $actor->exists) {
            throw new AuthorizationException('No tenés autorización para consultar esta agenda.');
        }

        // No confiar en roles o relaciones precargados en el objeto del guard.
        $actual = User::query()->find($actor->getAuthIdentifier());
        if ($actual === null || ! $actual->esKinesiologo()) {
            throw new AuthorizationException('No tenés autorización para consultar esta agenda.');
        }

        $kinesiologa = Kinesiologa::query()
            ->whereKey($kinesiologaId)
            ->where('user_id', $actual->getKey())
            ->first();
        if ($kinesiologa === null) {
            throw new AuthorizationException('No tenés autorización para consultar esta agenda.');
        }

        return $this->consulta->informacionDePeriodo($kinesiologa, $desde, $hasta);
    }
}
