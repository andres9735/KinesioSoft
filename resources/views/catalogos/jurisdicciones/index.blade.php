<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Jurisdicciones
                </h2>
                <p class="text-sm text-gray-500">
                    Administre las jurisdicciones disponibles del sistema.
                </p>
            </div>

            <a href="{{ route('catalogos.jurisdicciones.create') }}"
            class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                Nueva jurisdicción
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Listado de jurisdicciones</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $jurisdicciones->total() }} resultado(s) encontrado(s).
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">ID</th>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Slug</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($jurisdicciones as $jurisdiccion)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3 text-gray-700">{{ $jurisdiccion->id }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $jurisdiccion->nombre }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $jurisdiccion->slug }}</td>
                                    <td class="px-4 py-3">
                                        @if ($jurisdiccion->activo)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                                                Activa
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                Inactiva
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <a href="{{ route('catalogos.jurisdicciones.edit', $jurisdiccion) }}"
                                               class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('catalogos.jurisdicciones.toggle-activo', $jurisdiccion) }}"
                                                  class="js-confirm-action"
                                                  data-confirm-title="{{ $jurisdiccion->activo ? '¿Inactivar jurisdicción?' : '¿Activar jurisdicción?' }}"
                                                  data-confirm-text="{{ $jurisdiccion->activo
                                                        ? 'La jurisdicción dejará de estar disponible para nuevos formularios.'
                                                        : 'La jurisdicción volverá a estar disponible para su uso.' }}"
                                                  data-confirm-button="{{ $jurisdiccion->activo ? 'Sí, inactivar' : 'Sí, activar' }}"
                                                  data-cancel-button="Cancelar"
                                                  data-confirm-icon="{{ $jurisdiccion->activo ? 'warning' : 'question' }}">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                        class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $jurisdiccion->activo
                                                            ? 'bg-red-50 text-red-700 hover:bg-red-100'
                                                            : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                                                    {{ $jurisdiccion->activo ? 'Inactivar' : 'Activar' }}
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
                                                No hay jurisdicciones registradas
                                            </h4>

                                            <p class="mt-2 text-sm text-gray-500">
                                                Aún no se cargaron jurisdicciones en el sistema.
                                            </p>

                                            <div class="mt-6">
                                                <a
                                                    href="{{ route('catalogos.jurisdicciones.create') }}"
                                                    class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700"
                                                >
                                                    Crear primera jurisdicción
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t bg-white px-6 py-4">
                    {{ $jurisdicciones->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
