<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Nueva categoría clínica</h2>
            <p class="text-sm text-gray-500">Complete los datos de la categoría.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('catalogos.categorias.store') }}" class="space-y-6"
                  x-data="{
                      tipoSeleccionado: @js((string) old('tipo', '')),
                      ajustarPadre() {
                          const opcion = this.$refs.padre.options[this.$refs.padre.selectedIndex];
                          if (opcion && opcion.dataset.tipo && opcion.dataset.tipo !== this.tipoSeleccionado) {
                              this.$refs.padre.value = '';
                          }
                      }
                  }">
                @csrf
                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                    <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Datos de la categoría clínica</h3></div>
                    <div class="grid gap-6 p-6 sm:grid-cols-2">
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
                        <div>
                            <x-input-label for="tipo" value="Tipo" />
                            <select id="tipo" name="tipo" required x-model="tipoSeleccionado" @change="tipoSeleccionado = $event.target.value; ajustarPadre()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccione un tipo</option>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->value }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="categoria_padre_id" value="Categoría padre (opcional)" />
                            <select id="categoria_padre_id" name="categoria_padre_id" x-ref="padre" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sin categoría padre (categoría raíz)</option>
                                @foreach ($categoriasPadre as $padre)
                                    <option value="{{ $padre->id }}"
                                            data-tipo="{{ $padre->tipo->value }}"
                                            x-show="tipoSeleccionado === @js($padre->tipo->value)"
                                            :hidden="tipoSeleccionado !== @js($padre->tipo->value)"
                                            @selected(old('categoria_padre_id') == $padre->id)>{{ $padre->codigo }} · {{ $padre->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('categoria_padre_id')" class="mt-2" />
                        </div>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">La categoría se registrará activa.</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('catalogos.categorias.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                        <x-primary-button>Guardar categoría</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
