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

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                <x-catalogos.filtros
                    :action="route('catalogos.titulos.index')"
                    :q="$q"
                    :estado="$estado"
                    placeholder="Nombre del título profesional"
                />
                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Listado de títulos profesionales</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $titulos->total() }} resultado(s) encontrado(s).
                    </p>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($titulos as $titulo)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3 font-medium text-gray-900 break-words">{{ $titulo->nombre }}</td>
                                    <td class="px-4 py-3">
                                        <x-catalogos.estado-badge :activo="$titulo->activo" class="inline-flex items-center" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <a href="{{ route('catalogos.titulos.show', $titulo) }}"
                                               class="inline-flex items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200">
                                                Consultar
                                            </a>
                                            <a href="{{ route('catalogos.titulos.edit', $titulo) }}"
                                               class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                  action="{{ route($titulo->activo ? 'catalogos.titulos.inactivar' : 'catalogos.titulos.reactivar', $titulo) }}"
                                                  class="js-confirm-action"
                                                  data-confirm-title="{{ $titulo->activo ? '¿Inactivar título profesional?' : '¿Reactivar título profesional?' }}"
                                                  data-confirm-text="{{ $titulo->activo
                                                        ? 'El título profesional dejará de estar disponible para nuevos formularios.'
                                                        : 'El título profesional volverá a estar disponible para su uso.' }}"
                                                  data-confirm-button="{{ $titulo->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                                  data-cancel-button="Cancelar"
                                                  data-confirm-icon="{{ $titulo->activo ? 'warning' : 'question' }}">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                        class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $titulo->activo
                                                            ? 'bg-red-50 text-red-700 hover:bg-red-100'
                                                            : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                                                    {{ $titulo->activo ? 'Inactivar' : 'Reactivar' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-12">
                                        <x-catalogos.empty-state
                                            :titulo="$q !== '' || $estado !== 'todos' ? 'No se encontraron títulos profesionales' : 'No hay títulos profesionales registrados'"
                                            :descripcion="$q !== '' || $estado !== 'todos' ? 'Pruebe con otros criterios de búsqueda.' : 'Aún no se cargaron títulos profesionales en el sistema.'"
                                        >
                                            @if ($q === '' && $estado === 'todos')
                                            <div class="mt-6">
                                                <a
                                                    href="{{ route('catalogos.titulos.create') }}"
                                                    class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700"
                                                >
                                                    Crear primer título profesional
                                                </a>
                                            </div>
                                            @endif
                                        </x-catalogos.empty-state>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-100 md:hidden">
                    @forelse ($titulos as $titulo)
                        <article class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="min-w-0 flex-1 break-words font-semibold text-gray-900">{{ $titulo->nombre }}</h4>
                                <x-catalogos.estado-badge :activo="$titulo->activo" class="shrink-0" />
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('catalogos.titulos.show', $titulo) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700">Consultar</a>
                                <a href="{{ route('catalogos.titulos.edit', $titulo) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Editar</a>
                                <form method="POST"
                                      action="{{ route($titulo->activo ? 'catalogos.titulos.inactivar' : 'catalogos.titulos.reactivar', $titulo) }}"
                                      class="js-confirm-action"
                                      data-confirm-title="{{ $titulo->activo ? '¿Inactivar título profesional?' : '¿Reactivar título profesional?' }}"
                                      data-confirm-text="{{ $titulo->activo ? 'El título profesional dejará de estar disponible para nuevos formularios.' : 'El título profesional volverá a estar disponible para su uso.' }}"
                                      data-confirm-button="{{ $titulo->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                      data-cancel-button="Cancelar"
                                      data-confirm-icon="{{ $titulo->activo ? 'warning' : 'question' }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $titulo->activo ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">
                                        {{ $titulo->activo ? 'Inactivar' : 'Reactivar' }}
                                    </button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $q !== '' || $estado !== 'todos' ? 'No se encontraron títulos profesionales.' : 'No hay títulos profesionales registrados.' }}</p>
                    @endforelse
                </div>

                <div class="border-t bg-white px-6 py-4">
                    {{ $titulos->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
