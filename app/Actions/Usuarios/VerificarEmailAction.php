<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

final class VerificarEmailAction
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    public function ejecutar(User $user): bool
    {
        return DB::transaction(
            function () use ($user): bool {
                if ($user->hasVerifiedEmail()) {
                    return false;
                }

                if (! $user->markEmailAsVerified()) {
                    return false;
                }

                $this->auditoriaService->registrar(
                    evento: 'email_verificado',
                    modulo: 'Cuenta',
                    auditable: $user
                );

                return true;
            }
        );
    }
}
