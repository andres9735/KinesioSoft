<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Nueva jurisdicción
            </h2>
            <p class="text-sm text-gray-500">
                Registre una nueva jurisdicción emisora de matrícula.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST"
                  action="{{ route('catalogos.jurisdicciones.store') }}"
                  class="space-y-6">
                @csrf

                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                    <div class="border-b bg-gray-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">Datos de la jurisdicción</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            El slug se generará automáticamente a partir del nombre.
                        </p>
                    </div>

                    <div class="p-6">
                        <div>
                            <x-input-label for="nombre" :value="__('Nombre')" />
                            <x-text-input
                                id="nombre"
                                class="block mt-1 w-full"
                                type="text"
                                name="nombre"
                                :value="old('nombre')"
                                required
                                autofocus
                            />
                            <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                    <p class="text-sm text-gray-500">
                        Revise el nombre antes de guardar.
                    </p>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('catalogos.jurisdicciones.index') }}"
                           class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 font-semibold text-xs uppercase tracking-widest text-gray-800 hover:bg-gray-300">
                            Volver
                        </a>

                        <x-primary-button>
                            Guardar jurisdicción
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
