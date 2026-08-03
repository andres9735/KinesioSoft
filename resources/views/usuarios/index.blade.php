<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Módulo de Usuarios
                </h2>
                <p class="text-sm text-gray-500">
                    Administre usuarios, roles, estado de acceso y nivel de completitud del perfil.
                </p>
            </div>

            <a href="{{ route('usuarios.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                Nuevo usuario
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2H3V4zm0 4h14v8a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm3 2a1 1 0 000 2h2a1 1 0 100-2H6z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Filtros de búsqueda</h3>
                            <p class="text-sm text-gray-500">
                                Busque usuarios por nombre, email, rol o estado.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <form method="GET" action="{{ route('usuarios.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <x-input-label for="buscar" :value="__('Buscar')" />
                            <x-text-input
                                id="buscar"
                                name="buscar"
                                type="text"
                                class="block mt-1 w-full"
                                :value="request('buscar')"
                                placeholder="Nombre, apellido, email o DNI"
                            />
                        </div>

                        <div>
                            <x-input-label for="rol" :value="__('Rol')" />
                            <select
                                id="rol"
                                name="rol"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Todos</option>
                                <option value="superadmin" {{ request('rol') === 'superadmin' ? 'selected' : '' }}>Superadmin</option>
                                <option value="administrador" {{ request('rol') === 'administrador' ? 'selected' : '' }}>Administrador</option>
                                <option value="kinesiologo" {{ request('rol') === 'kinesiologo' ? 'selected' : '' }}>Kinesiólogo</option>
                                <option value="paciente" {{ request('rol') === 'paciente' ? 'selected' : '' }}>Paciente</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label for="activo" :value="__('Estado')" />
                            <select
                                id="activo"
                                name="activo"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Todos</option>
                                <option value="1" {{ request('activo') === '1' ? 'selected' : '' }}>Activo</option>
                                <option value="0" {{ request('activo') === '0' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-3">
                            <x-primary-button>
                                Filtrar
                            </x-primary-button>

                            <a href="{{ route('usuarios.index') }}"
                               class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 font-semibold text-xs uppercase tracking-widest text-gray-800 hover:bg-gray-300">
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Listado de usuarios</h3>
                            <p class="text-sm text-gray-500">
                                {{ $usuarios->total() }} resultado(s) encontrado(s).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">ID</th>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Apellido</th>
                                <th class="px-4 py-3 text-left font-semibold">Email</th>
                                <th class="px-4 py-3 text-left font-semibold">DNI</th>
                                <th class="px-4 py-3 text-left font-semibold">Rol</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold">Perfil</th>
                                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($usuarios as $usuario)
                                @php
                                    $perfilCompleto = $usuario->perfilCompleto();

                                    $rolTexto = $usuario->nombreRolPrincipal() ?: '—';

                                    $rolClase = match ($rolTexto) {
                                        'superadmin' => 'bg-purple-100 text-purple-700',
                                        'administrador' => 'bg-blue-100 text-blue-700',
                                        'kinesiologo' => 'bg-emerald-100 text-emerald-700',
                                        'paciente' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp

                                <tr class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3 text-gray-700">{{ $usuario->id }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $usuario->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $usuario->apellido ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $usuario->email }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $usuario->dni ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $rolClase }}">
                                            {{ ucfirst($rolTexto) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($usuario->activo)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                                                Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if (is_null($perfilCompleto))
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                                No aplica
                                            </span>
                                        @elseif ($perfilCompleto)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                                                Completo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-700">
                                                Incompleto
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <a href="{{ route('usuarios.edit', $usuario) }}"
                                               class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('usuarios.toggle-activo', $usuario) }}"
                                                  class="js-confirm-action"
                                                  data-confirm-title="{{ $usuario->activo ? '¿Inactivar usuario?' : '¿Activar usuario?' }}"
                                                  data-confirm-text="{{ $usuario->activo
                                                        ? 'El usuario no podrá iniciar sesión mientras permanezca inactivo.'
                                                        : 'El usuario podrá volver a iniciar sesión.' }}"
                                                  data-confirm-button="{{ $usuario->activo ? 'Sí, inactivar' : 'Sí, activar' }}"
                                                  data-cancel-button="Cancelar"
                                                  data-confirm-icon="{{ $usuario->activo ? 'warning' : 'question' }}">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                        class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $usuario->activo
                                                            ? 'bg-red-50 text-red-700 hover:bg-red-100'
                                                            : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                                                    {{ $usuario->activo ? 'Inactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                        No se encontraron usuarios.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t bg-white px-6 py-4">
                    {{ $usuarios->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
