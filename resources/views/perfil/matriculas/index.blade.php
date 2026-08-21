<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-gray-900">
                    Mis matrículas profesionales
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Administre sus credenciales profesionales registradas y consulte su estado actual.
                </p>
            </div>

            <a
                href="{{ route('perfil.kinesiologa.matriculas.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                <svg
                    class="h-4 w-4"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                    />
                </svg>

                Agregar matrícula
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-gray-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">
                            Matrículas registradas
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Las matrículas activas pueden utilizarse en las operaciones profesionales habilitadas por el sistema.
                        </p>
                    </div>

                    @if ($matriculas->count())
                        <div class="shrink-0">
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600"
                            >
                                {{ $matriculas->total() }}
                                {{ $matriculas->total() === 1 ? 'registro' : 'registros' }}
                            </span>
                        </div>
                    @endif
                </div>

                @if ($matriculas->count())
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/80">
                                    <th
                                        scope="col"
                                        class="whitespace-nowrap px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6"
                                    >
                                        Número
                                    </th>

                                    <th
                                        scope="col"
                                        class="whitespace-nowrap px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6"
                                    >
                                        Jurisdicción
                                    </th>

                                    <th
                                        scope="col"
                                        class="min-w-[260px] px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6"
                                    >
                                        Colegio emisor
                                    </th>

                                    <th
                                        scope="col"
                                        class="whitespace-nowrap px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6"
                                    >
                                        Estado
                                    </th>

                                    <th
                                        scope="col"
                                        class="whitespace-nowrap px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6"
                                    >
                                        Acciones
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                @foreach ($matriculas as $matricula)
                                    <tr class="transition hover:bg-gray-50/70">
                                        <td class="px-5 py-4 align-middle sm:px-6">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                        aria-hidden="true"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm2 4a1 1 0 000 2h8a1 1 0 100-2H6zm0 4a1 1 0 100 2h5a1 1 0 100-2H6z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </div>

                                                <span
                                                    class="whitespace-nowrap text-sm font-semibold text-gray-900"
                                                >
                                                    {{ $matricula->nro_matricula_profesional }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 align-middle sm:px-6">
                                            <span class="text-sm text-gray-700">
                                                {{ $matricula->jurisdiccion?->nombre ?? '—' }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-4 align-middle sm:px-6">
                                            <span class="text-sm leading-5 text-gray-700">
                                                {{ $matricula->colegioEmisor?->nombre ?? '—' }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-4 align-middle sm:px-6">
                                            @if ($matricula->activa)
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                                >
                                                    <span
                                                        class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                                    ></span>

                                                    Activa
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200"
                                                >
                                                    <span
                                                        class="h-1.5 w-1.5 rounded-full bg-gray-400"
                                                    ></span>

                                                    Inactiva
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-5 py-4 align-middle sm:px-6">
                                            <div
                                                class="flex flex-wrap items-center justify-end gap-2"
                                            >
                                                <a
                                                    href="{{ route('perfil.kinesiologa.matriculas.edit', $matricula) }}"
                                                    class="inline-flex items-center justify-center rounded-full bg-indigo-50 px-3.5 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
                                                >
                                                    Editar
                                                </a>

                                                <form
                                                    action="{{ route('perfil.kinesiologa.matriculas.toggle-activa', $matricula) }}"
                                                    method="POST"
                                                    class="inline js-confirm-action"
                                                    @if ($matricula->activa)
                                                        data-confirm-title="¿Inactivar matrícula?"
                                                        data-confirm-text="La matrícula dejará de estar disponible para nuevas operaciones profesionales. Si es su única matrícula activa, la operación será rechazada."
                                                        data-confirm-button="Sí, inactivar"
                                                        data-cancel-button="Cancelar"
                                                        data-confirm-icon="warning"
                                                    @else
                                                        data-confirm-title="¿Reactivar matrícula?"
                                                        data-confirm-text="La matrícula volverá a estar disponible para su uso profesional siempre que la jurisdicción y el colegio emisor asociados se encuentren activos."
                                                        data-confirm-button="Sí, reactivar"
                                                        data-cancel-button="Cancelar"
                                                        data-confirm-icon="question"
                                                    @endif
                                                >
                                                    @csrf
                                                    @method('PATCH')

                                                    <input
                                                        type="hidden"
                                                        name="activar"
                                                        value="{{ $matricula->activa ? '0' : '1' }}"
                                                    >

                                                    @if ($matricula->activa)
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center justify-center rounded-full bg-red-50 px-3.5 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                                                        >
                                                            Inactivar
                                                        </button>
                                                    @else
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center justify-center rounded-full bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
                                                        >
                                                            Reactivar
                                                        </button>
                                                    @endif
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($matriculas->hasPages())
                        <div class="border-t border-gray-100 px-5 py-4 sm:px-6">
                            {{ $matriculas->links() }}
                        </div>
                    @endif
                @else
                    <div class="px-5 py-14 text-center sm:px-6">
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500"
                        >
                            <svg
                                class="h-6 w-6"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm2 4a1 1 0 000 2h8a1 1 0 100-2H6zm0 4a1 1 0 100 2h5a1 1 0 100-2H6z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-4 text-base font-semibold text-gray-900">
                            No hay matrículas registradas
                        </h3>

                        <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                            Cuando registre una matrícula profesional, podrá administrarla desde este espacio.
                        </p>

                        <a
                            href="{{ route('perfil.kinesiologa.matriculas.create') }}"
                            class="mt-6 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                        >
                            Agregar matrícula
                        </a>
                    </div>
                @endif
            </div>

            <div
                class="mt-5 flex gap-3 rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3.5"
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
                    La jurisdicción y el colegio emisor forman parte de la procedencia de la matrícula y no pueden modificarse desde la edición ordinaria. Para corregir esos datos se requiere un procedimiento administrativo controlado.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
