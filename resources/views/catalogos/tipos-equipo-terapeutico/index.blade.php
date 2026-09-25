<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800">Tipos de equipo terapéutico</h2>
                <p class="text-sm text-gray-500">Administre las clases clínicas de aparatología del sistema.</p>
            </div>
            @can('create', App\Models\TipoEquipoTerapeutico::class)
                <a href="{{ route('catalogos.tipos-equipo.create') }}" class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-green-700">Nuevo tipo de equipo</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <x-catalogos.filtros :action="route('catalogos.tipos-equipo.index')" :q="$q" :estado="$estado" placeholder="Código o nombre del tipo de equipo" />

                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Listado de tipos de equipo terapéutico</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $tiposEquipo->total() }} resultado(s) encontrado(s).</p>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Código</th>
                                <th class="px-4 py-3 text-left font-semibold">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold">Categoría EQUIPAMIENTO</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($tiposEquipo as $tipoEquipoTerapeutico)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="max-w-40 break-words px-4 py-3 font-medium text-gray-900">{{ $tipoEquipoTerapeutico->codigo }}</td>
                                    <td class="max-w-xs break-words px-4 py-3 text-gray-900">{{ $tipoEquipoTerapeutico->nombre }}</td>
                                    <td class="max-w-xs break-words px-4 py-3 text-gray-700">{{ $tipoEquipoTerapeutico->categoriaClinica->codigo }} · {{ $tipoEquipoTerapeutico->categoriaClinica->nombre }}</td>
                                    <td class="px-4 py-3"><x-catalogos.estado-badge :activo="$tipoEquipoTerapeutico->activo" class="inline-flex items-center" /></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-3">
                                            @can('view', $tipoEquipoTerapeutico)
                                                <a href="{{ route('catalogos.tipos-equipo.show', $tipoEquipoTerapeutico) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Consultar</a>
                                            @endcan
                                            @can('update', $tipoEquipoTerapeutico)
                                                <a href="{{ route('catalogos.tipos-equipo.edit', $tipoEquipoTerapeutico) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Editar</a>
                                            @endcan
                                            @can($tipoEquipoTerapeutico->activo ? 'inactivar' : 'reactivar', $tipoEquipoTerapeutico)
                                                <form method="POST" action="{{ route($tipoEquipoTerapeutico->activo ? 'catalogos.tipos-equipo.inactivar' : 'catalogos.tipos-equipo.reactivar', $tipoEquipoTerapeutico) }}"
                                                      class="js-confirm-action"
                                                      data-confirm-title="{{ $tipoEquipoTerapeutico->activo ? '¿Inactivar tipo de equipo terapéutico?' : '¿Reactivar tipo de equipo terapéutico?' }}"
                                                      data-confirm-text="{{ $tipoEquipoTerapeutico->activo ? 'El tipo de equipo dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'El tipo de equipo volverá a estar disponible para nuevos usos.' }}"
                                                      data-confirm-button="{{ $tipoEquipoTerapeutico->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                                      data-cancel-button="Cancelar"
                                                      data-confirm-icon="{{ $tipoEquipoTerapeutico->activo ? 'warning' : 'question' }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $tipoEquipoTerapeutico->activo ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">{{ $tipoEquipoTerapeutico->activo ? 'Inactivar' : 'Reactivar' }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-12">
                                    <x-catalogos.empty-state
                                        :titulo="$q !== '' || $estado !== 'todos' ? 'No se encontraron tipos de equipo terapéutico' : 'No hay tipos de equipo terapéutico registrados'"
                                        :descripcion="$q !== '' || $estado !== 'todos' ? 'Pruebe con otros criterios de búsqueda.' : 'Aún no se cargaron tipos de equipo terapéutico en el sistema.'">
                                        @if ($q === '' && $estado === 'todos')
                                            @can('create', App\Models\TipoEquipoTerapeutico::class)
                                                <div class="mt-6"><a href="{{ route('catalogos.tipos-equipo.create') }}" class="inline-flex items-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">Crear primer tipo de equipo</a></div>
                                            @endcan
                                        @endif
                                    </x-catalogos.empty-state>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-100 md:hidden">
                    @forelse ($tiposEquipo as $tipoEquipoTerapeutico)
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-xs font-semibold text-gray-500">{{ $tipoEquipoTerapeutico->codigo }}</p>
                                    <h4 class="mt-1 break-words font-semibold text-gray-900">{{ $tipoEquipoTerapeutico->nombre }}</h4>
                                    <p class="mt-1 break-words text-sm text-gray-600">Categoría: {{ $tipoEquipoTerapeutico->categoriaClinica->codigo }} · {{ $tipoEquipoTerapeutico->categoriaClinica->nombre }}</p>
                                </div>
                                <x-catalogos.estado-badge :activo="$tipoEquipoTerapeutico->activo" class="shrink-0" />
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @can('view', $tipoEquipoTerapeutico)
                                    <a href="{{ route('catalogos.tipos-equipo.show', $tipoEquipoTerapeutico) }}" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700">Consultar</a>
                                @endcan
                                @can('update', $tipoEquipoTerapeutico)
                                    <a href="{{ route('catalogos.tipos-equipo.edit', $tipoEquipoTerapeutico) }}" class="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Editar</a>
                                @endcan
                                @can($tipoEquipoTerapeutico->activo ? 'inactivar' : 'reactivar', $tipoEquipoTerapeutico)
                                    <form method="POST" action="{{ route($tipoEquipoTerapeutico->activo ? 'catalogos.tipos-equipo.inactivar' : 'catalogos.tipos-equipo.reactivar', $tipoEquipoTerapeutico) }}"
                                          class="js-confirm-action"
                                          data-confirm-title="{{ $tipoEquipoTerapeutico->activo ? '¿Inactivar tipo de equipo terapéutico?' : '¿Reactivar tipo de equipo terapéutico?' }}"
                                          data-confirm-text="{{ $tipoEquipoTerapeutico->activo ? 'El tipo de equipo dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'El tipo de equipo volverá a estar disponible para nuevos usos.' }}"
                                          data-confirm-button="{{ $tipoEquipoTerapeutico->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                                          data-cancel-button="Cancelar"
                                          data-confirm-icon="{{ $tipoEquipoTerapeutico->activo ? 'warning' : 'question' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $tipoEquipoTerapeutico->activo ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">{{ $tipoEquipoTerapeutico->activo ? 'Inactivar' : 'Reactivar' }}</button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $q !== '' || $estado !== 'todos' ? 'No se encontraron tipos de equipo terapéutico.' : 'No hay tipos de equipo terapéutico registrados.' }}</p>
                    @endforelse
                </div>

                @if ($tiposEquipo->hasPages())
                    <div class="border-t px-4 py-4 sm:px-6">{{ $tiposEquipo->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
