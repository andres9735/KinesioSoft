<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Editar colegio emisor
            </h2>
            <p class="text-sm text-gray-500">
                Actualice los datos del colegio emisor seleccionado.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <form method="POST" action="{{ route('catalogos.colegios.update', $colegio) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        <div>
                            <x-input-label for="nombre" :value="__('Nombre del colegio emisor')" />
                            <x-text-input
                                id="nombre"
                                name="nombre"
                                type="text"
                                class="block mt-1 w-full"
                                :value="old('nombre', $colegio->nombre)"
                                required
                                autofocus
                                autocomplete="off"
                            />
                            <p class="mt-1 text-sm text-gray-500">
                                Modifique el nombre completo de la institución emisora.
                            </p>
                            <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <x-input-label for="sigla" :value="__('Sigla')" />
                                <x-text-input
                                    id="sigla"
                                    name="sigla"
                                    type="text"
                                    class="block mt-1 w-full uppercase"
                                    :value="old('sigla', $colegio->sigla)"
                                    required
                                    autocomplete="off"
                                />
                                <p class="mt-1 text-sm text-gray-500">
                                    La sigla se mantendrá normalizada en mayúsculas.
                                </p>
                                <x-input-error :messages="$errors->get('sigla')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="jurisdiccion_id" :value="__('Jurisdicción')" />
                                <select
                                    id="jurisdiccion_id"
                                    name="jurisdiccion_id"
                                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Seleccione una jurisdicción</option>
                                    @foreach ($jurisdicciones as $jurisdiccion)
                                        <option
                                            value="{{ $jurisdiccion->id }}"
                                            {{ (string) old('jurisdiccion_id', $colegio->jurisdiccion_id) === (string) $jurisdiccion->id ? 'selected' : '' }}
                                        >
                                            {{ $jurisdiccion->nombre }}
                                            @if (! $jurisdiccion->activo)
                                                (Inactiva)
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-gray-500">
                                    El colegio emisor permanecerá asociado a la jurisdicción seleccionada.
                                </p>
                                <x-input-error :messages="$errors->get('jurisdiccion_id')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 pt-4 sm:flex-row sm:justify-end">
                        <a href="{{ route('catalogos.colegios.index') }}"
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
