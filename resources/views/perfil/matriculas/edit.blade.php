<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Editar matrícula profesional
            </h2>
            <p class="text-sm text-gray-500">
                Actualice los datos de la matrícula profesional seleccionada.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M4 3a1 1 0 000 2h1v10a2 2 0 002 2h6a2 2 0 002-2V5h1a1 1 0 100-2H4z" />
                                <path d="M8 7a1 1 0 012 0v6a1 1 0 11-2 0V7zM12 7a1 1 0 112 0v6a1 1 0 11-2 0V7z" />
                            </svg>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Datos de la matrícula</h3>
                            <p class="text-sm text-gray-500">
                                Modifique la jurisdicción, el colegio emisor y el número de matrícula profesional.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('perfil.kinesiologa.matriculas.update', $matricula) }}"
                      class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="nro_matricula_profesional" :value="__('Número de matrícula profesional')" />
                            <x-text-input
                                id="nro_matricula_profesional"
                                class="block mt-1 w-full"
                                type="text"
                                name="nro_matricula_profesional"
                                :value="old('nro_matricula_profesional', $matricula->nro_matricula_profesional)"
                                required
                                autocomplete="off"
                            />
                            <p class="mt-1 text-sm text-gray-500">
                                Ingrese el número tal como figura en su credencial profesional.
                            </p>
                            <x-input-error :messages="$errors->get('nro_matricula_profesional')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="jurisdiccion_id" :value="__('Jurisdicción emisora')" />
                            <select
                                id="jurisdiccion_id"
                                name="jurisdiccion_id"
                                data-tom-select="simple"
                                data-tom-select-placeholder="Seleccione una jurisdicción"
                                data-jurisdiccion-colegio-target="#colegio_emisor_id"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Seleccione una jurisdicción</option>
                                @foreach ($jurisdicciones as $jurisdiccion)
                                    <option
                                        value="{{ $jurisdiccion->id }}"
                                        {{ (string) old('jurisdiccion_id', $matricula->jurisdiccion_id) === (string) $jurisdiccion->id ? 'selected' : '' }}
                                    >
                                        {{ $jurisdiccion->nombre }}@if (! $jurisdiccion->activo) (Inactiva) @endif
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('jurisdiccion_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="colegio_emisor_id" :value="__('Colegio emisor')" />
                            <select
                                id="colegio_emisor_id"
                                name="colegio_emisor_id"
                                data-tom-select="simple"
                                data-tom-select-placeholder="Primero seleccione una jurisdicción"
                                data-colegio-placeholder-sin-jurisdiccion="Primero seleccione una jurisdicción"
                                data-colegio-placeholder-con-jurisdiccion="Seleccione un colegio emisor"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Primero seleccione una jurisdicción</option>
                                @foreach ($colegiosEmisores as $colegio)
                                    <option
                                        value="{{ $colegio->id }}"
                                        data-jurisdiccion="{{ $colegio->jurisdiccion_id }}"
                                        {{ (string) old('colegio_emisor_id', $matricula->colegio_emisor_id) === (string) $colegio->id ? 'selected' : '' }}
                                    >
                                        {{ $colegio->nombre }}@if (! $colegio->activo) (Inactivo) @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Solo se mostrarán colegios correspondientes a la jurisdicción elegida.
                            </p>
                            <x-input-error :messages="$errors->get('colegio_emisor_id')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-b-2xl border-t bg-white px-6 py-4">
                        <p class="text-sm text-gray-500">
                            Revise los datos antes de guardar los cambios.
                        </p>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('perfil.kinesiologa.matriculas.index') }}"
                               class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                Cancelar
                            </a>

                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700">
                                Guardar cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
