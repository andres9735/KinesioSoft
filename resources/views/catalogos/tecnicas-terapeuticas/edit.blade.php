<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Editar técnica terapéutica</h2>
            <p class="text-sm text-gray-500">Actualice los datos de la técnica seleccionada.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogos.tecnicas.update', $tecnicaTerapeutica) }}" class="space-y-6">
                @csrf
                @method('PUT')
                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div class="border-b bg-gray-50 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">Datos de la técnica terapéutica</h3>
                    </div>
                    <div class="space-y-6 p-6">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="sistema_origen" value="Sistema de origen" />
                                <select id="sistema_origen" name="sistema_origen" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Seleccione un sistema</option>
                                    @foreach ($sistemasOrigen as $sistemaOrigen)
                                        <option value="{{ $sistemaOrigen->value }}" @selected(old('sistema_origen', $tecnicaTerapeutica->sistema_origen->value) === $sistemaOrigen->value)>{{ $sistemaOrigen->value }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('sistema_origen')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="codigo" value="Código" />
                                <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo', $tecnicaTerapeutica->codigo)" maxlength="255" required />
                                <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $tecnicaTerapeutica->nombre)" maxlength="255" required />
                            <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="descripcion" value="Descripción" />
                            <textarea id="descripcion" name="descripcion" rows="4"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion', $tecnicaTerapeutica->descripcion) }}</textarea>
                            <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">El estado se administra desde la acción Inactivar o Reactivar.</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('catalogos.tecnicas.index') }}" class="inline-flex items-center justify-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
