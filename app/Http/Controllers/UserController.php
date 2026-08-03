<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

        abort_unless(
            $usuarioAutenticado->hasAnyRole([
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]),
            403
        );

        $usuarios = User::query()
            ->with(['roles', 'paciente', 'kinesiologa'])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar')->toString();

                $query->where(function ($q) use ($buscar) {
                    $q->where('name', 'like', '%' . $buscar . '%')
                        ->orWhere('apellido', 'like', '%' . $buscar . '%')
                        ->orWhere('email', 'like', '%' . $buscar . '%')
                        ->orWhere('dni', 'like', '%' . $buscar . '%');
                });
            })
            ->when($request->filled('rol'), function ($query) use ($request) {
                $rol = $request->string('rol')->toString();

                $query->whereHas('roles', function ($q) use ($rol) {
                    $q->where('name', $rol);
                });
            })
            ->when($request->filled('activo'), function ($query) use ($request) {
                $activo = $request->string('activo')->toString();

                if ($activo === '1') {
                    $query->where('activo', true);
                }

                if ($activo === '0') {
                    $query->where('activo', false);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create(): View
    {
        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

        abort_unless(
            $usuarioAutenticado->hasAnyRole([
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]),
            403
        );

        $rolesDisponibles = $usuarioAutenticado->esSuperadmin()
            ? [
                RoleEnum::PACIENTE->value => 'Paciente',
                RoleEnum::KINESIOLOGO->value => 'Kinesiólogo',
                RoleEnum::ADMINISTRADOR->value => 'Administrador',
            ]
            : [
                RoleEnum::PACIENTE->value => 'Paciente',
                RoleEnum::KINESIOLOGO->value => 'Kinesiólogo',
            ];

        return view('usuarios.create', compact('rolesDisponibles'));
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

        abort_unless(
            $usuarioAutenticado->hasAnyRole([
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]),
            403
        );

        $rolesPermitidos = $usuarioAutenticado->esSuperadmin()
            ? [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
                RoleEnum::ADMINISTRADOR->value,
            ]
            : [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
            ];

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'rol' => ['required', Rule::in($rolesPermitidos)],
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'apellido' => $request->apellido,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'activo' => true,
            ]);

            $user->assignRole($request->rol);

            if ($request->rol === RoleEnum::PACIENTE->value) {
                $paciente = Paciente::create([
                    'user_id' => $user->id,
                    'nro_historia_clinica' => 'TEMP',
                    'observaciones' => null,
                ]);

                $paciente->update([
                    'nro_historia_clinica' => 'HC-' . str_pad($paciente->id, 6, '0', STR_PAD_LEFT),
                ]);
            }
        });

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

        abort_unless(
            $usuarioAutenticado->hasAnyRole([
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]),
            403
        );

        if ($usuarioAutenticado->esAdministrador() && ($user->esAdministrador() || $user->esSuperadmin())) {
            abort(403);
        }

        $user->load('roles');

        if ($user->esSuperadmin()) {
            $rolesDisponibles = [
                RoleEnum::SUPERADMIN->value => 'Superadmin',
            ];
        } else {
            $rolesDisponibles = $usuarioAutenticado->esSuperadmin()
                ? [
                    RoleEnum::PACIENTE->value => 'Paciente',
                    RoleEnum::KINESIOLOGO->value => 'Kinesiólogo',
                    RoleEnum::ADMINISTRADOR->value => 'Administrador',
                    RoleEnum::SUPERADMIN->value => 'Superadmin',
                ]
                : [
                    RoleEnum::PACIENTE->value => 'Paciente',
                    RoleEnum::KINESIOLOGO->value => 'Kinesiólogo',
                ];
        }

        $rolActual = $user->roles->pluck('name')->first();

        return view('usuarios.edit', compact('user', 'rolesDisponibles', 'rolActual'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

        abort_unless(
            $usuarioAutenticado->hasAnyRole([
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]),
            403
        );

        if ($usuarioAutenticado->esAdministrador() && ($user->esAdministrador() || $user->esSuperadmin())) {
            abort(403);
        }

        $rolesPermitidos = $usuarioAutenticado->esSuperadmin()
            ? [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]
            : [
                RoleEnum::PACIENTE->value,
                RoleEnum::KINESIOLOGO->value,
            ];

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'rol' => ['required', Rule::in($rolesPermitidos)],
        ]);

        if ($user->esSuperadmin() && $request->rol !== RoleEnum::SUPERADMIN->value) {
            abort(403, 'No se puede cambiar el rol de un superadmin desde esta pantalla.');
        }

        DB::transaction(function () use ($request, $user) {
            $user->load('roles');

            $rolAnterior = $user->roles->pluck('name')->first();
            $rolNuevo = $request->rol;

            $user->update([
                'name' => $request->name,
                'apellido' => $request->apellido,
                'email' => $request->email,
            ]);

            if ($rolAnterior !== $rolNuevo) {
                $user->syncRoles([$rolNuevo]);

                if ($rolNuevo === RoleEnum::PACIENTE->value && ! $user->paciente) {
                    $paciente = Paciente::create([
                        'user_id' => $user->id,
                        'nro_historia_clinica' => 'TEMP',
                        'observaciones' => null,
                    ]);

                    $paciente->update([
                        'nro_historia_clinica' => 'HC-' . str_pad($paciente->id, 6, '0', STR_PAD_LEFT),
                    ]);
                }
            }
        });

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleActivo(User $user): RedirectResponse
    {
        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

        abort_unless(
            $usuarioAutenticado->hasAnyRole([
                RoleEnum::ADMINISTRADOR->value,
                RoleEnum::SUPERADMIN->value,
            ]),
            403
        );

        if (
            $usuarioAutenticado->esAdministrador()
            && ($user->esAdministrador() || $user->esSuperadmin())
        ) {
            abort(403);
        }

        if ($user->esSuperadmin() && $user->activo) {
            $cantidadSuperadminsActivos = User::query()
                ->where('activo', true)
                ->whereHas('roles', function ($query) {
                    $query->where(
                        'name',
                        RoleEnum::SUPERADMIN->value
                    );
                })
                ->count();

            if ($cantidadSuperadminsActivos <= 1) {
                return redirect()
                    ->route('usuarios.index')
                    ->with(
                        'warning',
                        'No se puede desactivar al único superadmin activo.'
                    );
            }
        }

        DB::transaction(function () use ($user) {
            $user->activo = ! (bool) $user->activo;

            if (! $user->activo) {
                $user->remember_token = null;
            }

            $user->save();

            if (
                ! $user->activo
                && config('session.driver') === 'database'
            ) {
                $sessionTable = config('session.table') ?: 'sessions';

                DB::table($sessionTable)
                    ->where('user_id', $user->getKey())
                    ->delete();
            }
        });

        $mensaje = $user->activo
            ? 'Usuario activado correctamente.'
            : 'Usuario inactivado correctamente.';

        return redirect()
            ->route('usuarios.index')
            ->with('success', $mensaje);
    }
}
