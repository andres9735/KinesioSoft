<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Usuarios\VerificarEmailAction;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __construct(
        private readonly VerificarEmailAction $verificarEmailAction
    ) {}

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(
        EmailVerificationRequest $request
    ): RedirectResponse {
        $verificado = $this->verificarEmailAction->ejecutar(
            $request->user()
        );

        if ($verificado) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(
            route('dashboard', absolute: false) . '?verified=1'
        );
    }
}
