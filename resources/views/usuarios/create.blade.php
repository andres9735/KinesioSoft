<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Nuevo usuario
            </h2>
            <p class="text-sm text-gray-500">
                Registre un nuevo usuario y defina su rol inicial dentro del sistema.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0 text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10A8 8 0 114.293 4.293 8 8 0 0118 10zm-8-4a1 1 0 00-1 1v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-indigo-900">
                            Alta de usuario
                        </p>
                        <p class="text-sm text-indigo-800">
                            Complete los datos básicos del usuario. Los datos complementarios se cargarán luego según el rol asignado.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('usuarios.store') }}"
                  class="space-y-6">
                @csrf

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
                                <h3 class="text-lg font-semibold text-gray-800">Datos básicos</h3>
                                <p class="text-sm text-gray-500">
                                    Información inicial del usuario dentro del sistema.
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
                                :value="old('name')"
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
                                :value="old('apellido')"
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
                                :value="old('email')"
                                required
                            />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password" :value="__('Contraseña')" />
                            <x-text-input
                                id="password"
                                class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required
                            />
                            <p class="mt-1 text-sm text-gray-500">
                                Debe tener al menos 8 caracteres.
                            </p>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" :value="__('Confirmar contraseña')" />
                            <x-text-input
                                id="password_confirmation"
                                class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation"
                                required
                            />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="rol" :value="__('Rol')" />
                            <select
                                id="rol"
                                name="rol"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Seleccione un rol</option>
                                @foreach ($rolesDisponibles as $valor => $label)
                                    <option value="{{ $valor }}" {{ old('rol') === $valor ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('rol')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                    <div class="border-b bg-gray-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">Información según el rol</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            El comportamiento del sistema cambia según el tipo de usuario creado.
                        </p>
                    </div>

                    <div class="p-6 space-y-3 text-sm text-gray-700">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <p><strong>Paciente:</strong> se generará automáticamente su número de historia clínica y completará su perfil luego.</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <p><strong>Kinesiólogo:</strong> completará sus datos profesionales al iniciar sesión.</p>
                        </div>

                        @if (auth()->user()->esSuperadmin())
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                                <p><strong>Administrador:</strong> tendrá acceso interno al sistema.</p>
                            </div>
                        @endif
                    </div>
                </section>

                <div class="flex items-center justify-between rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                    <p class="text-sm text-gray-500">
                        Revise los datos antes de registrar el nuevo usuario.
                    </p>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('usuarios.index') }}"
                           class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 font-semibold text-xs uppercase tracking-widest text-gray-800 hover:bg-gray-300">
                            Volver
                        </a>

                        <x-primary-button>
                            Guardar usuario
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
