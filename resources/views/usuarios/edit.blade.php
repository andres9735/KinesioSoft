<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Editar usuario
            </h2>
            <p class="text-sm text-gray-500">
                Actualice los datos básicos y el rol del usuario.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0 text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a2 2 0 01-.878.497l-3 1a1 1 0 01-1.265-1.265l1-3a2 2 0 01.497-.878l8.5-8.5a2 2 0 012.828 0z" />
                            <path fill-rule="evenodd" d="M4 16a1 1 0 011-1h11a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-indigo-900">
                            Modo edición
                        </p>
                        <p class="text-sm text-indigo-800">
                            Actualice la información del usuario y revise cuidadosamente cualquier cambio de rol.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('usuarios.update', $user) }}"
                  class="space-y-6">
                @csrf
                @method('PUT')

                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                    <div class="border-b bg-gray-50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 2a4 4 0 100 8 4 4 0 000-8z" />
                                    <path fill-rule="evenodd" d="M.458 16.042A8 8 0 0110 10a8 8 0 019.542 6.042A1 1 0 0118.57 17H1.43a1 1 0 01-.972-1.958z" clip-rule="evenodd" />
                                </svg>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Datos del usuario</h3>
                                <p class="text-sm text-gray-500">
                                    Información básica y rol asignado.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="name" :value="__('Nombre')" />
                            <x-text-input
                                id="name"
                                class="block mt-1 w-full"
                                type="text"
                                name="name"
                                :value="old('name', $user->name)"
                                required
                                autofocus
                            />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="apellido" :value="__('Apellido')" />
                            <x-text-input
                                id="apellido"
                                class="block mt-1 w-full"
                                type="text"
                                name="apellido"
                                :value="old('apellido', $user->apellido)"
                                required
                            />
                            <x-input-error :messages="$errors->get('apellido')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input
                                id="email"
                                class="block mt-1 w-full"
                                type="email"
                                name="email"
                                :value="old('email', $user->email)"
                                required
                            />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="rol" :value="__('Rol')" />
                            <select
                                id="rol"
                                name="rol"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                @foreach ($rolesDisponibles as $valor => $label)
                                    <option value="{{ $valor }}" {{ old('rol', $rolActual) === $valor ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('rol')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                    <p class="text-sm text-gray-500">
                        Revise los cambios antes de actualizar el usuario.
                    </p>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('usuarios.index') }}"
                           class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 font-semibold text-xs uppercase tracking-widest text-gray-800 hover:bg-gray-300">
                            Volver
                        </a>

                        <x-primary-button>
                            Guardar cambios
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
