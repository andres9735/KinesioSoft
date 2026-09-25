<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Colegios emisores
                </h2>
                <p class="text-sm text-gray-500">
                    Administre los colegios emisores disponibles para la carga de matrículas profesionales.
                </p>
            </div>

            <a href="{{ route('catalogos.colegios.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                Nuevo colegio emisor
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <form method="GET" action="{{ route('catalogos.colegios.index') }}" class="mb-6 grid gap-4 border-b border-gray-100 pb-6 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
                    <div>
                        <label for="q" class="block text-sm font-medium text-gray-700">Buscar por nombre o sigla</label>
                        <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Nombre o sigla del colegio"
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="todos" @selected($estado === 'todos')>Todos</option>
                            <option value="activos" @selected($estado === 'activos')>Activos</option>
                            <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 sm:flex-none">Buscar</button>
                        <a href="{{ route('catalogos.colegios.index') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:flex-none">Limpiar filtros</a>
                    </div>
                </form>
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Listado de colegios emisores
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $colegios->total() }} resultado(s) encontrado(s). Consulte, edite y administre su estado.
                        </p>
                    </div>
                </div>

                @if ($colegios->count())
                    <div class="hidden overflow-hidden rounded-2xl border border-gray-100 md:block">
                        <div class="overflow-x-auto">
                            <table class="w-full table-fixed divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="w-[25%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Nombre
                                        </th>
                                        <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Sigla
                                        </th>
                                        <th class="w-[21%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Jurisdicción
                                        </th>
                                        <th class="w-[15%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Estado
                                        </th>
                                        <th class="w-[27%] px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach ($colegios as $colegio)
                                        <tr class="hover:bg-gray-50/70 transition">
                                            <td class="px-3 py-3 align-middle">
                                                <div class="break-words text-sm font-semibold text-gray-900">
                                                    {{ $colegio->nombre }}
                                                </div>
                                            </td>

                                            <td class="px-3 py-3 align-middle">
                                                <span class="inline-flex max-w-full items-center break-all rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-700">
                                                    {{ $colegio->sigla }}
                                                </span>
                                            </td>

                                            <td class="px-3 py-3 align-middle">
                                                <div class="break-words text-sm text-gray-700">
                                                    {{ $colegio->jurisdiccion?->nombre ?? 'Sin jurisdicción' }}
                                                </div>
                                            </td>

                                            <td class="px-3 py-3 align-middle">
                                                <x-catalogos.estado-badge :activo="$colegio->activo" class="inline-flex items-center" />
                                            </td>

                                            <td class="px-3 py-3 align-middle">
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <a href="{{ route('catalogos.colegios.show', $colegio) }}"
                                                       class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200">
                                                        Consultar
                                                    </a>
                                                    <a href="{{ route('catalogos.colegios.edit', $colegio) }}"
                                                       class="inline-flex items-center rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                                        Editar
                                                    </a>

                                                    <form action="{{ route($colegio->activo ? 'catalogos.colegios.inactivar' : 'catalogos.colegios.reactivar', $colegio) }}"
                                                          method="POST"
                                                          class="inline js-confirm-action"
                                                          @if ($colegio->activo)
                                                              data-confirm-title="¿Inactivar colegio emisor?"
                                                              data-confirm-text="El colegio emisor dejará de estar disponible para nuevas asignaciones, pero se conservará el historial relacionado."
                                                              data-confirm-button="Sí, inactivar"
                                                              data-cancel-button="Cancelar"
                                                              data-confirm-icon="warning"
                                                          @else
                                                              data-confirm-title="¿Reactivar colegio emisor?"
                                                              data-confirm-text="El colegio emisor volverá a estar disponible si su jurisdicción está activa."
                                                              data-confirm-button="Sí, reactivar"
                                                              data-cancel-button="Cancelar"
                                                              data-confirm-icon="question"
                                                          @endif>
                                                        @csrf
                                                        @method('PATCH')

                                                        @if ($colegio->activo)
                                                            <button type="submit"
                                                                    class="inline-flex items-center rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100">
                                                                Inactivar
                                                            </button>
                                                        @else
                                                            <button type="submit"
                                                                    class="inline-flex items-center rounded-lg bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 transition hover:bg-green-100">
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
                    </div>

                    <div class="divide-y divide-gray-100 rounded-2xl border border-gray-100 md:hidden">
                        @foreach ($colegios as $colegio)
                            <article class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="min-w-0 flex-1 break-words font-semibold text-gray-900">{{ $colegio->nombre }}</h4>
                                    <x-catalogos.estado-badge :activo="$colegio->activo" class="shrink-0" />
                                </div>
                                <p class="break-words text-sm text-gray-600">
                                    <span class="font-semibold">{{ $colegio->sigla }}</span>
                                    <span aria-hidden="true"> · </span>
                                    Jurisdicción: {{ $colegio->jurisdiccion?->nombre ?? 'Sin jurisdicción' }}
                                </p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('catalogos.colegios.show', $colegio) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700">Consultar</a>
                                    <a href="{{ route('catalogos.colegios.edit', $colegio) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Editar</a>
                                    <form method="POST"
                                          action="{{ route($colegio->activo ? 'catalogos.colegios.inactivar' : 'catalogos.colegios.reactivar', $colegio) }}"
                                          class="js-confirm-action"
                                          data-confirm-title="{{ $colegio->activo ? '¿Inactivar colegio emisor?' : '¿Reactivar colegio emisor?' }}"
                                          data-confirm-text="{{ $colegio->activo ? 'El colegio emisor dejará de estar disponible para nuevas asignaciones, pero se conservará el historial relacionado.' : 'El colegio emisor volverá a estar disponible si su jurisdicción está activa.' }}"
                                          data-confirm-button="{{ $colegio->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                          data-cancel-button="Cancelar"
                                          data-confirm-icon="{{ $colegio->activo ? 'warning' : 'question' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $colegio->activo ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">
                                            {{ $colegio->activo ? 'Inactivar' : 'Reactivar' }}
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $colegios->links() }}
                    </div>
                @else
                    <x-catalogos.empty-state
                        :titulo="$q !== '' || $estado !== 'todos' ? 'No se encontraron colegios emisores' : 'No hay colegios emisores registrados'"
                        :descripcion="$q !== '' || $estado !== 'todos' ? 'Pruebe con otros criterios de búsqueda.' : 'Aún no se cargaron colegios emisores en el sistema.'"
                    >
                        @if ($q === '' && $estado === 'todos')
                            <div class="mt-6">
                                <a href="{{ route('catalogos.colegios.create') }}"
                                   class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                                    Crear primer colegio emisor
                                </a>
                            </div>
                        @endif
                    </x-catalogos.empty-state>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
