<x-app-layout>
    @php
        $user = auth()->user();

        $esEdicion =
            !empty($user->dni) &&
            !empty($user->fecha_nacimiento) &&
            !empty($user->sexo) &&
            !empty($user->telefono);
    @endphp

    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                {{ $esEdicion ? 'Editar perfil' : 'Completar perfil' }}
            </h2>
            <p class="text-sm text-gray-500">
                {{ $esEdicion
                    ? 'Actualice sus datos personales.'
                    : 'Complete sus datos personales para poder continuar usando el sistema.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0 text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10A8 8 0 114.293 4.293 8 8 0 0118 10zm-7-4a1 1 0 10-2 0v4a1 1 0 00.293.707l2.5 2.5a1 1 0 101.414-1.414L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-indigo-900">
                            {{ $esEdicion ? 'Modo edición' : 'Primer ingreso del paciente' }}
                        </p>
                        <p class="text-sm text-indigo-800">
                            {{ $esEdicion
                                ? 'Revise y mantenga actualizada la información registrada.'
                                : 'Necesitamos estos datos para completar correctamente su perfil en el sistema.' }}
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('perfil.actualizar') }}"
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
                                <h3 class="text-lg font-semibold text-gray-800">Datos personales</h3>
                                <p class="text-sm text-gray-500">
                                    Información básica del paciente dentro del sistema.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="dni" :value="__('DNI')" />
                            <x-text-input
                                id="dni"
                                class="block mt-1 w-full"
                                type="text"
                                name="dni"
                                :value="old('dni', $user->dni)"
                                required
                            />
                            <p class="mt-1 text-sm text-gray-500">
                                Ingrese el DNI sin puntos.
                            </p>
                            <x-input-error :messages="$errors->get('dni')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="telefono" :value="__('Teléfono')" />
                            <x-text-input
                                id="telefono"
                                class="block mt-1 w-full"
                                type="text"
                                name="telefono"
                                :value="old('telefono', $user->telefono)"
                                required
                            />
                            <p class="mt-1 text-sm text-gray-500">
                                Puede ingresar números, espacios, guiones o paréntesis.
                            </p>
                            <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="fecha_nacimiento" :value="__('Fecha de nacimiento')" />
                            <x-text-input
                                id="fecha_nacimiento"
                                class="block mt-1 w-full"
                                type="date"
                                name="fecha_nacimiento"
                                :value="old('fecha_nacimiento', $user->fecha_nacimiento ? \Carbon\Carbon::parse($user->fecha_nacimiento)->format('Y-m-d') : '')"
                                required
                            />
                            <x-input-error :messages="$errors->get('fecha_nacimiento')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sexo" :value="__('Sexo')" />
                            <select
                                id="sexo"
                                name="sexo"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Seleccione una opción</option>
                                <option value="masculino" {{ old('sexo', $user->sexo) === 'masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="femenino" {{ old('sexo', $user->sexo) === 'femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="otro" {{ old('sexo', $user->sexo) === 'otro' ? 'selected' : '' }}>Otro</option>
                                <option value="prefiero_no_decir" {{ old('sexo', $user->sexo) === 'prefiero_no_decir' ? 'selected' : '' }}>Prefiero no decir</option>
                            </select>
                            <x-input-error :messages="$errors->get('sexo')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                    <p class="text-sm text-gray-500">
                        {{ $esEdicion
                            ? 'Revise los datos antes de guardar los cambios.'
                            : 'Una vez guardado, podrá continuar normalmente dentro del sistema.' }}
                    </p>

                    <x-primary-button>
                        {{ $esEdicion ? 'Guardar cambios' : 'Guardar y continuar' }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
