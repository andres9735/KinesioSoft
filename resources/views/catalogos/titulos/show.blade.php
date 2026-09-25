<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Consultar título profesional</h2>
            <p class="text-sm text-gray-500">Datos del título profesional seleccionado.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Datos del título profesional</h3>
                </div>
                <dl class="grid gap-6 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                        <dd class="mt-1 text-base font-semibold text-gray-900">{{ $titulo->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Estado</dt>
                        <dd class="mt-1 text-base font-semibold {{ $titulo->activo ? 'text-green-700' : 'text-red-700' }}">
                            {{ $titulo->activo ? 'Activo' : 'Inactivo' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                <a href="{{ route('catalogos.titulos.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                <a href="{{ route('catalogos.titulos.edit', $titulo) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700">Editar</a>
            </div>
        </div>
    </div>
</x-app-layout>
