<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Nuevo tipo de equipo terapéutico</h2>
            <p class="text-sm text-gray-500">Registre una clase clínica de aparatología.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogos.tipos-equipo.store') }}" class="space-y-6">
                @csrf
                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Datos del tipo de equipo terapéutico</h3></div>
                    <div class="space-y-6 p-6">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="codigo" value="Código" />
                                <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo')" maxlength="255" required />
                                <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="nombre" value="Nombre" />
                                <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre')" maxlength="255" required />
                                <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="categoria_clinica_id" value="Categoría clínica de equipamiento" />
                            <select id="categoria_clinica_id" name="categoria_clinica_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccione una categoría</option>
                                @foreach ($categoriasDisponibles as $categoria)
                                    <option value="{{ $categoria->id }}" @selected(old('categoria_clinica_id') == $categoria->id)>{{ $categoria->codigo }} · {{ $categoria->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('categoria_clinica_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="descripcion" value="Descripción" />
                            <textarea id="descripcion" name="descripcion" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion') }}</textarea>
                            <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
                        </div>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">El tipo de equipo se registrará activo.</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('catalogos.tipos-equipo.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                        <x-primary-button>Guardar tipo de equipo</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
