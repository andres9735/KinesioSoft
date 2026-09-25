<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Consultar categoría clínica</h2>
            <p class="text-sm text-gray-500">Datos de la categoría seleccionada.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Datos de la categoría clínica</h3></div>
                <dl class="grid gap-6 p-6 sm:grid-cols-2">
                    <div><dt class="text-sm font-medium text-gray-500">Código</dt><dd class="mt-1 break-words text-base font-semibold text-gray-900">{{ $categoriaClinica->codigo }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Tipo</dt><dd class="mt-1 text-base font-semibold text-gray-900">{{ $categoriaClinica->tipo->value }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Nombre</dt><dd class="mt-1 break-words text-base font-semibold text-gray-900">{{ $categoriaClinica->nombre }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Categoría padre</dt><dd class="mt-1 break-words text-base text-gray-900">{{ $categoriaClinica->padre ? $categoriaClinica->padre->codigo.' · '.$categoriaClinica->padre->nombre : 'Sin categoría padre (categoría raíz)' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Estado</dt><dd class="mt-1"><x-catalogos.estado-badge :activo="$categoriaClinica->activo" class="inline-flex items-center" /></dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Categorías hijas directas</h3></div>
                <div class="p-6">
                    @forelse ($categoriaClinica->hijas as $hija)
                        <p class="break-words py-1 text-sm text-gray-800">{{ $hija->codigo }} · {{ $hija->nombre }}</p>
                    @empty
                        <p class="text-sm text-gray-500">Sin categorías hijas.</p>
                    @endforelse
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                <a href="{{ route('catalogos.categorias.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                @can('update', $categoriaClinica)
                    <a href="{{ route('catalogos.categorias.edit', $categoriaClinica) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700">Editar</a>
                @endcan
                @can($categoriaClinica->activo ? 'inactivar' : 'reactivar', $categoriaClinica)
                    <form method="POST" action="{{ route($categoriaClinica->activo ? 'catalogos.categorias.inactivar' : 'catalogos.categorias.reactivar', $categoriaClinica) }}"
                          class="js-confirm-action"
                          data-confirm-title="{{ $categoriaClinica->activo ? '¿Inactivar categoría clínica?' : '¿Reactivar categoría clínica?' }}"
                          data-confirm-text="{{ $categoriaClinica->activo ? 'La categoría dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'La categoría volverá a estar disponible para nuevos usos.' }}"
                          data-confirm-button="{{ $categoriaClinica->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                          data-cancel-button="Cancelar"
                          data-confirm-icon="{{ $categoriaClinica->activo ? 'warning' : 'question' }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest {{ $categoriaClinica->activo ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">{{ $categoriaClinica->activo ? 'Inactivar' : 'Reactivar' }}</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
