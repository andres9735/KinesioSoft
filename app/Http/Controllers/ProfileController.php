<?php

namespace App\Http\Controllers;

use App\Actions\Usuarios\ActualizarCuentaPropiaAction;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ActualizarCuentaPropiaAction $actualizarCuentaPropiaAction
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(
        ProfileUpdateRequest $request
    ): RedirectResponse {
        $this->actualizarCuentaPropiaAction->ejecutar(
            $request->user(),
            $request->validated()
        );

        return Redirect::route('profile.edit')
            ->with('status', 'profile-updated');
    }
}
