<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Editar especialidad de kinesiología
            </h2>
            <p class="text-sm text-gray-500">
                Actualice el nombre de la especialidad seleccionada.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <form method="POST" action="{{ route('catalogos.especialidades.update', $especialidad) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="nombre" :value="__('Nombre de la especialidad')" />
                        <x-text-input
                            id="nombre"
                            name="nombre"
                            type="text"
                            class="block mt-1 w-full"
                            :value="old('nombre', $especialidad->nombre)"
                            required
                            autofocus
                            autocomplete="off"
                        />
                        <p class="mt-1 text-sm text-gray-500">
                            Modifique el nombre de la especialidad seleccionada.
                        </p>
                        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                    </div>

                    <div class="flex flex-col-reverse gap-3 pt-4 sm:flex-row sm:justify-end">
                        <a href="{{ route('catalogos.especialidades.index') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                            Cancelar
                        </a>

                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
