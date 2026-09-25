<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Consultar técnica terapéutica</h2>
            <p class="text-sm text-gray-500">Datos de la técnica seleccionada.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Datos de la técnica terapéutica</h3>
                </div>
                <dl class="grid gap-6 p-6 sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="text-sm font-medium text-gray-500">Código</dt>
                        <dd class="mt-1 break-words text-base font-semibold text-gray-900">{{ $tecnicaTerapeutica->codigo }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-sm font-medium text-gray-500">Sistema origen</dt>
                        <dd class="mt-1 text-base font-semibold text-gray-900">{{ $tecnicaTerapeutica->sistema_origen->value }}</dd>
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                        <dd class="mt-1 break-words text-base font-semibold text-gray-900">{{ $tecnicaTerapeutica->nombre }}</dd>
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Descripción</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-base text-gray-900">{{ $tecnicaTerapeutica->descripcion ?? 'Sin descripción' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Estado</dt>
                        <dd class="mt-1"><x-catalogos.estado-badge :activo="$tecnicaTerapeutica->activo" class="inline-flex items-center" /></dd>
                    </div>
                </dl>
            </section>

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                <a href="{{ route('catalogos.tecnicas.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                @can('update', $tecnicaTerapeutica)
                    <a href="{{ route('catalogos.tecnicas.edit', $tecnicaTerapeutica) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700">Editar</a>
                @endcan
                @can($tecnicaTerapeutica->activo ? 'inactivar' : 'reactivar', $tecnicaTerapeutica)
                    <form method="POST"
                          action="{{ route($tecnicaTerapeutica->activo ? 'catalogos.tecnicas.inactivar' : 'catalogos.tecnicas.reactivar', $tecnicaTerapeutica) }}"
                          class="js-confirm-action"
                          data-confirm-title="{{ $tecnicaTerapeutica->activo ? '¿Inactivar técnica terapéutica?' : '¿Reactivar técnica terapéutica?' }}"
                          data-confirm-text="{{ $tecnicaTerapeutica->activo ? 'La técnica dejará de estar disponible para nuevos usos y conservará sus referencias históricas.' : 'La técnica volverá a estar disponible para nuevos usos.' }}"
                          data-confirm-button="{{ $tecnicaTerapeutica->activo ? 'Sí, inactivar' : 'Sí, reactivar' }}"
                          data-cancel-button="Cancelar"
                          data-confirm-icon="{{ $tecnicaTerapeutica->activo ? 'warning' : 'question' }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest {{ $tecnicaTerapeutica->activo ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                            {{ $tecnicaTerapeutica->activo ? 'Inactivar' : 'Reactivar' }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
