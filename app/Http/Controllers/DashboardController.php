<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        [$dashboardPartial, $rolLabel] = match (true) {
            $user->esSuperadmin() => [
                'dashboard.partials.administrativo',
                'Superadministrador',
            ],

            $user->esAdministrador() => [
                'dashboard.partials.administrativo',
                'Administrador',
            ],

            $user->esKinesiologo() => [
                'dashboard.partials.kinesiologo',
                'Kinesiólogo',
            ],

            $user->esPaciente() => [
                'dashboard.partials.paciente',
                'Paciente',
            ],

            default => abort(403),
        };

        return view('dashboard', [
            'user' => $user,
            'dashboardPartial' => $dashboardPartial,
            'rolLabel' => $rolLabel,
        ]);
    }
}
