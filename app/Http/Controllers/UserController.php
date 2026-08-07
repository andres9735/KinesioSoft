<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Paciente;
use App\Models\User;
use App\Services\GeneradorNumeroHistoriaClinica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly GeneradorNumeroHistoriaClinica $generadorNumeroHistoriaClinica
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

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
        Gate::authorize('create', User::class);

        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

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

    public function store(
        StoreUserRequest $request
    ): RedirectResponse {
        Gate::authorize('create', User::class);

        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'activo' => true,
            ]);

            $user->assignRole($data['rol']);

            if ($data['rol'] === RoleEnum::PACIENTE->value) {
                $paciente = Paciente::create([
                    'user_id' => $user->id,
                    'nro_historia_clinica' => 'TEMP',
                    'observaciones' => null,
                ]);

                $paciente->update([
                    'nro_historia_clinica' =>
                    $this->generadorNumeroHistoriaClinica->generar($paciente),
                ]);
            }
        });

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        /** @var User $usuarioAutenticado */
        $usuarioAutenticado = Auth::user();

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

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        Gate::authorize('update', $user);

        $data = $request->validated();

        Gate::authorize('changeRole', [$user, $data['rol']]);

        DB::transaction(function () use ($data, $user): void {
            $user->load('roles');

            $rolAnterior = $user->roles->pluck('name')->first();
            $rolNuevo = $data['rol'];

            $user->update([
                'name' => $data['name'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
            ]);

            if ($rolAnterior !== $rolNuevo) {
                $user->syncRoles([$rolNuevo]);

                if (
                    $rolNuevo === RoleEnum::PACIENTE->value
                    && ! $user->paciente
                ) {
                    $paciente = Paciente::create([
                        'user_id' => $user->id,
                        'nro_historia_clinica' => 'TEMP',
                        'observaciones' => null,
                    ]);

                    $paciente->update([
                        'nro_historia_clinica' =>
                        $this->generadorNumeroHistoriaClinica->generar($paciente),
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
        Gate::authorize('toggleActivo', $user);

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
