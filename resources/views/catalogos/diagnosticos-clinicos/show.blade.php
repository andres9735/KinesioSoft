<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Consultar diagnóstico clínico</h2>
            <p class="text-sm text-gray-500">Datos del diagnóstico seleccionado.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Datos del diagnóstico clínico</h3>
                </div>
                <dl class="grid gap-6 p-6 sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="text-sm font-medium text-gray-500">Código</dt>
                        <dd class="mt-1 break-words text-base font-semibold text-gray-900">{{ $diagnosticoClinico->codigo }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-sm font-medium text-gray-500">Sistema origen</dt>
                        <dd class="mt-1 text-base font-semibold text-gray-900">{{ $diagnosticoClinico->sistema_origen->value }}</dd>
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                        <dd class="mt-1 break-words text-base font-semibold text-gray-900">{{ $diagnosticoClinico->nombre }}</dd>
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Descripción</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-base text-gray-900">{{ $diagnosticoClinico->descripcion ?? 'Sin descripción' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Estado</dt>
                        <dd class="mt-1"><x-catalogos.estado-badge :activo="$diagnosticoClinico->activo" class="inline-flex items-center" /></dd>
                    </div>
                </dl>
            </section>

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                <a href="{{ route('catalogos.diagnosticos.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                @can('update', $diagnosticoClinico)
                    <a href="{{ route('catalogos.diagnosticos.edit', $diagnosticoClinico) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700">Editar</a>
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
                        <button type="submit" class="inline-flex items-center rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest {{ $diagnosticoClinico->activo ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                            {{ $diagnosticoClinico->activo ? 'Inactivar' : 'Reactivar' }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
