<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-gray-900">
                Editar matrícula profesional
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Actualice el número de matrícula profesional asociado a esta credencial.
            </p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="border-b border-gray-100 px-5 py-5 sm:px-6">
                    <div class="flex items-start justify-between gap-4">
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
                                        d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a2 2 0 01-.878.513l-3 1A1 1 0 013.77 14.16l1-3a2 2 0 01.513-.878l8.5-8.5a2 2 0 012.828 0l.803.804z"
                                    />
                                    <path
                                        d="M3 17a1 1 0 100 2h14a1 1 0 100-2H3z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Datos de la matrícula
                                </h3>

                                <p class="mt-1 text-sm leading-5 text-gray-500">
                                    Solo puede modificarse el número de matrícula profesional.
                                </p>
                            </div>
                        </div>

                        @if ($matricula->activa)
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Activa
                            </span>
                        @else
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                Inactiva
                            </span>
                        @endif
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('perfil.kinesiologa.matriculas.update', $matricula) }}"
                >
                    @csrf
                    @method('PUT')

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
                                :value="old(
                                    'nro_matricula_profesional',
                                    $matricula->nro_matricula_profesional
                                )"
                                required
                                autocomplete="off"
                                autofocus
                            />

                            <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                Ingrese el número exactamente como figura en su credencial profesional.
                            </p>

                            <x-input-error
                                :messages="$errors->get('nro_matricula_profesional')"
                                class="mt-2"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <span class="block text-sm font-medium text-gray-700">
                                    Jurisdicción emisora
                                </span>

                                <div
                                    class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm text-gray-800">
                                            {{ $matricula->jurisdiccion?->nombre ?? '—' }}
                                        </span>

                                        @if (
                                            $matricula->jurisdiccion
                                            && ! $matricula->jurisdiccion->activo
                                        )
                                            <span
                                                class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
                                            >
                                                Inactiva
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                    La jurisdicción no puede modificarse desde esta operación.
                                </p>
                            </div>

                            <div>
                                <span class="block text-sm font-medium text-gray-700">
                                    Colegio emisor
                                </span>

                                <div
                                    class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm leading-5 text-gray-800">
                                            {{ $matricula->colegioEmisor?->nombre ?? '—' }}
                                        </span>

                                        @if (
                                            $matricula->colegioEmisor
                                            && ! $matricula->colegioEmisor->activo
                                        )
                                            <span
                                                class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
                                            >
                                                Inactivo
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                    El colegio emisor forma parte de la procedencia histórica de la matrícula.
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex gap-3 rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3.5"
                        >
                            <svg
                                class="mt-0.5 h-5 w-5 shrink-0 text-blue-500"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M18 10A8 8 0 11.001 9.999 8 8 0 0118 10zm-8-3a1 1 0 100 2 1 1 0 000-2zm1 7a1 1 0 10-2 0v-3a1 1 0 012 0v3z"
                                    clip-rule="evenodd"
                                />
                            </svg>

                            <p class="text-sm leading-5 text-blue-700">
                                La jurisdicción y el colegio emisor son datos inmutables de la credencial. Si alguno requiere una corrección excepcional, deberá realizarse mediante un procedimiento administrativo controlado.
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
                                    d="M17.414 2.586a2 2 0 010 2.828l-8.5 8.5a2 2 0 01-.878.513l-3 1A1 1 0 013.77 14.16l1-3a2 2 0 01.513-.878l8.5-8.5a2 2 0 012.828 0l.803.804z"
                                />
                            </svg>

                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            <p class="mt-4 text-center text-xs leading-5 text-gray-400">
                La actualización conserva la jurisdicción, el colegio emisor y el estado actual de la matrícula.
            </p>
        </div>
    </div>
</x-app-layout>
