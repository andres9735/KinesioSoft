<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Títulos profesionales
                </h2>
                <p class="text-sm text-gray-500">
                    Administre los títulos habilitantes del sistema.
                </p>
            </div>

            <a href="{{ route('catalogos.titulos.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                Nuevo título
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-2xl ring-1 ring-gray-100 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Nombre
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Slug
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($titulos as $titulo)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $titulo->id }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ $titulo->nombre }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $titulo->slug }}
                                </td>

                                <td class="px-6 py-4">
                                    @if($titulo->activo)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <a href="{{ route('catalogos.titulos.edit', $titulo) }}"
                                           class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                            Editar
                                        </a>

                                        <form method="POST"
                                              action="{{ route('catalogos.titulos.toggle-activo', $titulo) }}"
                                              class="inline js-confirm-action"
                                              data-confirm-title="¿Está seguro?"
                                              data-confirm-text="Está por {{ $titulo->activo ? 'inactivar' : 'activar' }} este título profesional. Esta acción puede revertirse."
                                              data-confirm-button="Sí, continuar"
                                              data-cancel-button="Cancelar"
                                              data-confirm-icon="{{ $titulo->activo ? 'warning' : 'question' }}">
                                            @csrf
                                            @method('PATCH')

                                            <button type="submit"
                                                    class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $titulo->activo
                                                        ? 'bg-red-50 text-red-700 hover:bg-red-100'
                                                        : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                                                {{ $titulo->activo ? 'Inactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12">
                                    <div class="mx-auto max-w-lg rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center">
                                        <h4 class="text-base font-semibold text-gray-900">
                                            No hay títulos profesionales registrados
                                        </h4>

                                        <p class="mt-2 text-sm text-gray-500">
                                            Aún no se cargaron títulos profesionales en el sistema.
                                        </p>

                                        <div class="mt-6">
                                            <a
                                                href="{{ route('catalogos.titulos.create') }}"
                                                class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700"
                                            >
                                                Crear primer título profesional
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4">
                    {{ $titulos->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
