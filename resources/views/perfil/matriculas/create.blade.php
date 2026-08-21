<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-gray-900">
                Nueva matrícula profesional
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Registre una credencial profesional correspondiente a una nueva jurisdicción.
            </p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="border-b border-gray-100 px-5 py-5 sm:px-6">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm6 3a1 1 0 011 1v2h2a1 1 0 110 2h-2v2a1 1 0 11-2 0v-2H7a1 1 0 110-2h2V7a1 1 0 011-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>

                        <div>
                            <h3 class="text-base font-semibold text-gray-900">
                                Datos de la matrícula
                            </h3>

                            <p class="mt-1 text-sm leading-5 text-gray-500">
                                Complete la jurisdicción, el colegio emisor y el número tal como figuran en su credencial profesional.
                            </p>
                        </div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('perfil.kinesiologa.matriculas.store') }}"
                >
                    @csrf

                    <div class="space-y-6 px-5 py-6 sm:px-6">
                        <div>
                            <x-input-label
                                for="nro_matricula_profesional"
                                :value="__('Número de matrícula profesional')"
                            />

                            <x-text-input
                                id="nro_matricula_profesional"
                                class="mt-1 block w-full"
                                type="text"
                                name="nro_matricula_profesional"
                                :value="old('nro_matricula_profesional')"
                                required
                                autocomplete="off"
                                autofocus
                            />

                            <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                Ingrese el número exactamente como aparece en la matrícula o credencial emitida.
                            </p>

                            <x-input-error
                                :messages="$errors->get('nro_matricula_profesional')"
                                class="mt-2"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <x-input-label
                                    for="jurisdiccion_id"
                                    :value="__('Jurisdicción emisora')"
                                />

                                <select
                                    id="jurisdiccion_id"
                                    name="jurisdiccion_id"
                                    data-tom-select="simple"
                                    data-tom-select-placeholder="Seleccione una jurisdicción"
                                    data-jurisdiccion-colegio-target="#colegio_emisor_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">
                                        Seleccione una jurisdicción
                                    </option>

                                    @foreach ($jurisdicciones as $jurisdiccion)
                                        <option
                                            value="{{ $jurisdiccion->id }}"
                                            {{ (string) old('jurisdiccion_id') === (string) $jurisdiccion->id ? 'selected' : '' }}
                                        >
                                            {{ $jurisdiccion->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                    Puede registrar como máximo una matrícula por jurisdicción.
                                </p>

                                <x-input-error
                                    :messages="$errors->get('jurisdiccion_id')"
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <x-input-label
                                    for="colegio_emisor_id"
                                    :value="__('Colegio emisor')"
                                />

                                <select
                                    id="colegio_emisor_id"
                                    name="colegio_emisor_id"
                                    data-tom-select="simple"
                                    data-tom-select-placeholder="Primero seleccione una jurisdicción"
                                    data-colegio-placeholder-sin-jurisdiccion="Primero seleccione una jurisdicción"
                                    data-colegio-placeholder-con-jurisdiccion="Seleccione un colegio emisor"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">
                                        Primero seleccione una jurisdicción
                                    </option>

                                    @foreach ($colegiosEmisores as $colegio)
                                        <option
                                            value="{{ $colegio->id }}"
                                            data-jurisdiccion="{{ $colegio->jurisdiccion_id }}"
                                            {{ (string) old('colegio_emisor_id') === (string) $colegio->id ? 'selected' : '' }}
                                        >
                                            {{ $colegio->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                    Se mostrarán únicamente los colegios correspondientes a la jurisdicción seleccionada.
                                </p>

                                <x-input-error
                                    :messages="$errors->get('colegio_emisor_id')"
                                    class="mt-2"
                                />
                            </div>
                        </div>

                        <div
                            class="flex gap-3 rounded-xl border border-amber-100 bg-amber-50/70 px-4 py-3.5"
                        >
                            <svg
                                class="mt-0.5 h-5 w-5 shrink-0 text-amber-500"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.596C19.01 16.03 18.047 17.5 16.518 17.5H3.482c-1.529 0-2.492-1.47-1.742-2.805L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>

                            <p class="text-sm leading-5 text-amber-700">
                                Una vez registrada la matrícula, la jurisdicción y el colegio emisor quedarán asociados de forma permanente. La edición ordinaria permitirá modificar únicamente el número de matrícula.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-col-reverse gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6"
                    >
                        <a
                            href="{{ route('perfil.kinesiologa.matriculas.index') }}"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                        >
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"
                                    clip-rule="evenodd"
                                />
                            </svg>

                            Guardar matrícula
                        </button>
                    </div>
                </form>
            </div>

            <p class="mt-4 text-center text-xs leading-5 text-gray-400">
                Los datos registrados deben coincidir con la documentación profesional vigente.
            </p>
        </div>
    </div>
</x-app-layout>
