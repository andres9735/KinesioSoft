<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Especialidades de kinesiología
                </h2>
                <p class="text-sm text-gray-500">
                    Administre las especialidades disponibles para la carga del perfil profesional.
                </p>
            </div>

            <a href="{{ route('catalogos.especialidades.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                Nueva especialidad
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Listado de especialidades
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Consulte, edite y administre el estado de las especialidades registradas.
                    </p>
                </div>

                @if ($especialidades->count())
                    <div class="overflow-hidden rounded-2xl border border-gray-100">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Nombre
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Estado
                                        </th>
                                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach ($especialidades as $especialidad)
                                        <tr class="hover:bg-gray-50/70 transition">
                                            <td class="px-6 py-4 align-middle">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    {{ $especialidad->nombre }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 align-middle">
                                                @if ($especialidad->activo)
                                                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-200">
                                                        Activo
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-200">
                                                        Inactivo
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 align-middle">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="{{ route('catalogos.especialidades.edit', $especialidad) }}"
                                                       class="inline-flex items-center rounded-lg bg-indigo-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 transition hover:bg-indigo-100">
                                                        Editar
                                                    </a>

                                                    <form action="{{ route('catalogos.especialidades.toggle-activo', $especialidad) }}"
                                                          method="POST"
                                                          class="inline js-confirm-action"
                                                          @if ($especialidad->activo)
                                                              data-confirm-title="¿Inactivar especialidad?"
                                                              data-confirm-text="La especialidad dejará de estar disponible para nuevas asignaciones, pero se conservará el historial relacionado."
                                                              data-confirm-button="Sí, inactivar"
                                                              data-cancel-button="Cancelar"
                                                              data-confirm-icon="warning"
                                                          @else
                                                              data-confirm-title="¿Activar especialidad?"
                                                              data-confirm-text="La especialidad volverá a estar disponible para su uso en el sistema."
                                                              data-confirm-button="Sí, activar"
                                                              data-cancel-button="Cancelar"
                                                              data-confirm-icon="question"
                                                          @endif>
                                                        @csrf
                                                        @method('PATCH')

                                                        @if ($especialidad->activo)
                                                            <button type="submit"
                                                                    class="inline-flex items-center rounded-lg bg-red-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-700 transition hover:bg-red-100">
                                                                Inactivar
                                                            </button>
                                                        @else
                                                            <button type="submit"
                                                                    class="inline-flex items-center rounded-lg bg-green-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-green-700 transition hover:bg-green-100">
                                                                Activar
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
                    </div>

                    <div class="mt-6">
                        {{ $especialidades->links() }}
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-12 text-center">
                        <h3 class="text-base font-semibold text-gray-900">
                            No hay especialidades registradas
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Aún no se cargaron especialidades de kinesiología en el sistema.
                        </p>

                        <div class="mt-6">
                            <a href="{{ route('catalogos.especialidades.create') }}"
                               class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                                Crear primera especialidad
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
