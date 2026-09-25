<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">Ejercicios domiciliarios</h2>
                <p class="text-sm text-gray-500">Administre ejercicios, clasificación clínica y recursos multimedia.</p>
            </div>
            @can('create', App\Models\EjercicioDomiciliario::class)
                <a href="{{ route('catalogos.ejercicios.create') }}" class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">Nuevo ejercicio</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <x-catalogos.filtros :action="route('catalogos.ejercicios.index')" :q="$q" :estado="$estado" placeholder="Código o nombre del ejercicio" />

                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Listado de ejercicios domiciliarios</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $ejercicios->total() }} resultado(s) encontrado(s).</p>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Código</th>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Dificultad</th>
                                <th class="px-4 py-3 text-left font-semibold">Categorías</th>
                                <th class="px-4 py-3 text-left font-semibold">Recursos</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($ejercicios as $ejercicioDomiciliario)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="max-w-40 break-words px-4 py-3 font-medium text-gray-900">{{ $ejercicioDomiciliario->codigo }}</td>
                                    <td class="max-w-xs break-words px-4 py-3 text-gray-900">{{ $ejercicioDomiciliario->nombre }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ejercicioDomiciliario->dificultad_base->value }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ejercicioDomiciliario->categorias_count }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $ejercicioDomiciliario->recursos_count }} total · {{ $ejercicioDomiciliario->recursos_activos_count }} activos</td>
                                    <td class="px-4 py-3"><x-catalogos.estado-badge :activo="$ejercicioDomiciliario->activo" class="inline-flex items-center" /></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            @can('view', $ejercicioDomiciliario)
                                                <a href="{{ route('catalogos.ejercicios.show', $ejercicioDomiciliario) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Consultar</a>
                                            @endcan
                                            @can('update', $ejercicioDomiciliario)
                                                <a href="{{ route('catalogos.ejercicios.edit', $ejercicioDomiciliario) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Editar</a>
                                            @endcan
                                            @can($ejercicioDomiciliario->activo ? 'inactivar' : 'reactivar', $ejercicioDomiciliario)
                                                <form method="POST" action="{{ route($ejercicioDomiciliario->activo ? 'catalogos.ejercicios.inactivar' : 'catalogos.ejercicios.reactivar', $ejercicioDomiciliario) }}"
                                                      class="js-confirm-action"
                                                      data-confirm-title="{{ $ejercicioDomiciliario->activo ? '¿Inactivar ejercicio domiciliario?' : '¿Reactivar ejercicio domiciliario?' }}"
                                                      data-confirm-text="{{ $ejercicioDomiciliario->activo ? 'El ejercicio dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'El ejercicio volverá a estar disponible si cumple las reglas clínicas vigentes.' }}"
                                                      data-confirm-button="{{ $ejercicioDomiciliario->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                                      data-cancel-button="Cancelar"
                                                      data-confirm-icon="{{ $ejercicioDomiciliario->activo ? 'warning' : 'question' }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $ejercicioDomiciliario->activo ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">{{ $ejercicioDomiciliario->activo ? 'Inactivar' : 'Reactivar' }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-12">
                                    <x-catalogos.empty-state
                                        :titulo="$q !== '' || $estado !== 'todos' ? 'No se encontraron ejercicios domiciliarios' : 'No hay ejercicios domiciliarios registrados'"
                                        :descripcion="$q !== '' || $estado !== 'todos' ? 'Pruebe con otros criterios de búsqueda.' : 'Aún no se cargaron ejercicios domiciliarios en el sistema.'">
                                        @if ($q === '' && $estado === 'todos')
                                            @can('create', App\Models\EjercicioDomiciliario::class)
                                                <div class="mt-6"><a href="{{ route('catalogos.ejercicios.create') }}" class="inline-flex items-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">Crear primer ejercicio</a></div>
                                            @endcan
                                        @endif
                                    </x-catalogos.empty-state>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-100 md:hidden">
                    @forelse ($ejercicios as $ejercicioDomiciliario)
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-xs font-semibold text-gray-500">{{ $ejercicioDomiciliario->codigo }}</p>
                                    <h4 class="mt-1 break-words font-semibold text-gray-900">{{ $ejercicioDomiciliario->nombre }}</h4>
                                    <p class="mt-1 text-sm text-gray-600">{{ $ejercicioDomiciliario->dificultad_base->value }} · {{ $ejercicioDomiciliario->categorias_count }} cat. · {{ $ejercicioDomiciliario->recursos_activos_count }} rec. activos</p>
                                </div>
                                <x-catalogos.estado-badge :activo="$ejercicioDomiciliario->activo" class="shrink-0" />
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @can('view', $ejercicioDomiciliario)
                                    <a href="{{ route('catalogos.ejercicios.show', $ejercicioDomiciliario) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700">Consultar</a>
                                @endcan
                                @can('update', $ejercicioDomiciliario)
                                    <a href="{{ route('catalogos.ejercicios.edit', $ejercicioDomiciliario) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Editar</a>
                                @endcan
                                @can($ejercicioDomiciliario->activo ? 'inactivar' : 'reactivar', $ejercicioDomiciliario)
                                    <form method="POST" action="{{ route($ejercicioDomiciliario->activo ? 'catalogos.ejercicios.inactivar' : 'catalogos.ejercicios.reactivar', $ejercicioDomiciliario) }}"
                                          class="js-confirm-action"
                                          data-confirm-title="{{ $ejercicioDomiciliario->activo ? '¿Inactivar ejercicio domiciliario?' : '¿Reactivar ejercicio domiciliario?' }}"
                                          data-confirm-text="{{ $ejercicioDomiciliario->activo ? 'El ejercicio dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'El ejercicio volverá a estar disponible si cumple las reglas clínicas vigentes.' }}"
                                          data-confirm-button="{{ $ejercicioDomiciliario->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                          data-cancel-button="Cancelar"
                                          data-confirm-icon="{{ $ejercicioDomiciliario->activo ? 'warning' : 'question' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $ejercicioDomiciliario->activo ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">{{ $ejercicioDomiciliario->activo ? 'Inactivar' : 'Reactivar' }}</button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $q !== '' || $estado !== 'todos' ? 'No se encontraron ejercicios domiciliarios.' : 'No hay ejercicios domiciliarios registrados.' }}</p>
                    @endforelse
                </div>

                @if ($ejercicios->hasPages())
                    <div class="border-t bg-white px-6 py-4">{{ $ejercicios->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
