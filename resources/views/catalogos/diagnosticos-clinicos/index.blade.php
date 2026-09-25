<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">Diagnósticos clínicos</h2>
                <p class="text-sm text-gray-500">Administre los diagnósticos clínicos disponibles del sistema.</p>
            </div>
            @can('create', App\Models\DiagnosticoClinico::class)
                <a href="{{ route('catalogos.diagnosticos.create') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">
                    Nuevo diagnóstico
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <form method="GET" action="{{ route('catalogos.diagnosticos.index') }}"
                      class="grid gap-4 border-b border-gray-100 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end sm:p-6">
                    <div>
                        <label for="q" class="block text-sm font-medium text-gray-700">Buscar por código o nombre</label>
                        <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Código o nombre del diagnóstico"
                               class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
                        <select id="estado" name="estado"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="todos" @selected($estado === 'todos')>Todos</option>
                            <option value="activos" @selected($estado === 'activos')>Activos</option>
                            <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 sm:flex-none">Buscar</button>
                        <a href="{{ route('catalogos.diagnosticos.index') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:flex-none">Limpiar filtros</a>
                    </div>
                </form>

                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Listado de diagnósticos clínicos</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $diagnosticos->total() }} resultado(s) encontrado(s).</p>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Código</th>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Sistema origen</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($diagnosticos as $diagnosticoClinico)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="max-w-40 break-words px-4 py-3 font-medium text-gray-900">{{ $diagnosticoClinico->codigo }}</td>
                                    <td class="max-w-xs break-words px-4 py-3 text-gray-900">{{ $diagnosticoClinico->nombre }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $diagnosticoClinico->sistema_origen->value }}</td>
                                    <td class="px-4 py-3"><x-catalogos.estado-badge :activo="$diagnosticoClinico->activo" class="inline-flex items-center" /></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            @can('view', $diagnosticoClinico)
                                                <a href="{{ route('catalogos.diagnosticos.show', $diagnosticoClinico) }}" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200">Consultar</a>
                                            @endcan
                                            @can('update', $diagnosticoClinico)
                                                <a href="{{ route('catalogos.diagnosticos.edit', $diagnosticoClinico) }}" class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Editar</a>
                                            @endcan
                                            @can($diagnosticoClinico->activo ? 'inactivar' : 'reactivar', $diagnosticoClinico)
                                                <form method="POST"
                                                      action="{{ route($diagnosticoClinico->activo ? 'catalogos.diagnosticos.inactivar' : 'catalogos.diagnosticos.reactivar', $diagnosticoClinico) }}"
                                                      class="js-confirm-action"
                                                      data-confirm-title="{{ $diagnosticoClinico->activo ? '¿Inactivar diagnóstico clínico?' : '¿Reactivar diagnóstico clínico?' }}"
                                                      data-confirm-text="{{ $diagnosticoClinico->activo ? 'El diagnóstico dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'El diagnóstico volverá a estar disponible para nuevos usos.' }}"
                                                      data-confirm-button="{{ $diagnosticoClinico->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                                      data-cancel-button="Cancelar"
                                                      data-confirm-icon="{{ $diagnosticoClinico->activo ? 'warning' : 'question' }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold transition {{ $diagnosticoClinico->activo ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                                                        {{ $diagnosticoClinico->activo ? 'Inactivar' : 'Reactivar' }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12">
                                        <x-catalogos.empty-state
                                            :titulo="$q !== '' || $estado !== 'todos' ? 'No se encontraron diagnósticos clínicos' : 'No hay diagnósticos clínicos registrados'"
                                            :descripcion="$q !== '' || $estado !== 'todos' ? 'Pruebe con otros criterios de búsqueda.' : 'Aún no se cargaron diagnósticos clínicos en el sistema.'"
                                        >
                                            @if ($q === '' && $estado === 'todos')
                                                @can('create', App\Models\DiagnosticoClinico::class)
                                                    <div class="mt-6">
                                                        <a href="{{ route('catalogos.diagnosticos.create') }}" class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">Crear primer diagnóstico</a>
                                                    </div>
                                                @endcan
                                            @endif
                                        </x-catalogos.empty-state>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-100 md:hidden">
                    @forelse ($diagnosticos as $diagnosticoClinico)
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-xs font-semibold text-gray-500">{{ $diagnosticoClinico->codigo }}</p>
                                    <h4 class="mt-1 break-words font-semibold text-gray-900">{{ $diagnosticoClinico->nombre }}</h4>
                                    <p class="mt-1 break-words text-sm text-gray-600">Sistema: {{ $diagnosticoClinico->sistema_origen->value }}</p>
                                </div>
                                <x-catalogos.estado-badge :activo="$diagnosticoClinico->activo" class="shrink-0" />
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @can('view', $diagnosticoClinico)
                                    <a href="{{ route('catalogos.diagnosticos.show', $diagnosticoClinico) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700">Consultar</a>
                                @endcan
                                @can('update', $diagnosticoClinico)
                                    <a href="{{ route('catalogos.diagnosticos.edit', $diagnosticoClinico) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Editar</a>
                                @endcan
                                @can($diagnosticoClinico->activo ? 'inactivar' : 'reactivar', $diagnosticoClinico)
                                    <form method="POST"
                                          action="{{ route($diagnosticoClinico->activo ? 'catalogos.diagnosticos.inactivar' : 'catalogos.diagnosticos.reactivar', $diagnosticoClinico) }}"
                                          class="js-confirm-action"
                                          data-confirm-title="{{ $diagnosticoClinico->activo ? '¿Inactivar diagnóstico clínico?' : '¿Reactivar diagnóstico clínico?' }}"
                                          data-confirm-text="{{ $diagnosticoClinico->activo ? 'El diagnóstico dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'El diagnóstico volverá a estar disponible para nuevos usos.' }}"
                                          data-confirm-button="{{ $diagnosticoClinico->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                          data-cancel-button="Cancelar"
                                          data-confirm-icon="{{ $diagnosticoClinico->activo ? 'warning' : 'question' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $diagnosticoClinico->activo ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">
                                            {{ $diagnosticoClinico->activo ? 'Inactivar' : 'Reactivar' }}
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $q !== '' || $estado !== 'todos' ? 'No se encontraron diagnósticos clínicos.' : 'No hay diagnósticos clínicos registrados.' }}</p>
                    @endforelse
                </div>

                <div class="border-t bg-white px-6 py-4">{{ $diagnosticos->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
