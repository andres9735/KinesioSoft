<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Editar ejercicio domiciliario</h2>
            <p class="text-sm text-gray-500">Actualice el ejercicio, su clasificación y sus recursos.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogos.ejercicios.update', $ejercicioDomiciliario) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                @include('catalogos.ejercicios-domiciliarios.partials.form')
                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">El estado del ejercicio se administra desde Inactivar o Reactivar.</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('catalogos.ejercicios.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
