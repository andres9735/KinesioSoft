@php
    $recursosPersistidosPorId = collect($recursosIniciales)->keyBy('id');
    $recursosFormulario = collect(old('recursos', $recursosIniciales))->map(function ($recurso) use ($recursosPersistidosPorId) {
        $recurso = is_array($recurso) ? $recurso : [];
        $persistido = isset($recurso['id']) ? $recursosPersistidosPorId->get((int) $recurso['id']) : null;

        return [
            'id' => $recurso['id'] ?? null,
            'tipo' => $recurso['tipo'] ?? 'IMAGEN',
            'origen' => $recurso['origen'] ?? 'LOCAL',
            'origen_original' => $persistido['origen_original'] ?? null,
            'titulo' => $recurso['titulo'] ?? '',
            'orden' => $recurso['orden'] ?? 0,
            'activo' => array_key_exists('activo', $recurso) ? (bool) $recurso['activo'] : true,
            'url_externa' => $recurso['url_externa'] ?? '',
            'tiene_archivo_actual' => (bool) ($persistido['tiene_archivo_actual'] ?? false),
        ];
    })->values()->all();
@endphp

<section aria-labelledby="recursos-multimedia" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100"
         x-data="ejercicioCatalogo({ recursos: @js($recursosFormulario) })">
    <div class="flex flex-col gap-3 border-b bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 id="recursos-multimedia" class="text-lg font-semibold text-gray-800">5. Recursos multimedia</h3>
            <p class="mt-1 text-sm text-gray-500">Agregue archivos locales o enlaces externos; los recursos persistidos se conservan históricamente.</p>
        </div>
        <button type="button" @click="agregarRecurso()" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Agregar recurso</button>
    </div>

    <div class="space-y-4 p-6">
        <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Por seguridad, los archivos locales nuevos o de reemplazo deben seleccionarse nuevamente después de un error de validación.
        </p>
        <x-input-error :messages="$errors->get('recursos')" />
        @foreach ($errors->get('recursos.*') as $mensajes)
            <x-input-error :messages="$mensajes" />
        @endforeach

        <template x-if="recursos.length === 0">
            <div class="rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center text-sm text-gray-500">No hay recursos cargados. Puede guardar el ejercicio sin recursos.</div>
        </template>

        <template x-for="(recurso, index) in recursos" :key="recurso.key">
            <article class="space-y-5 rounded-xl border border-gray-200 p-4">
                <input type="hidden" :name="`recursos[${index}][id]`" :value="recurso.id || ''" :disabled="!recurso.id">
                <input type="hidden" :name="`recursos[${index}][orden]`" :value="recurso.orden">
                <input type="hidden" :name="`recursos[${index}][activo]`" value="0">

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-gray-900" x-text="recurso.id ? `Recurso persistido #${recurso.id}` : 'Recurso nuevo'"></h4>
                        <p class="mt-1 text-xs text-gray-500" x-text="`Orden ${recurso.orden}`"></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="moverArriba(index)" :disabled="index === 0" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Subir</button>
                        <button type="button" @click="moverAbajo(index)" :disabled="index === recursos.length - 1" class="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Bajar</button>
                        <button type="button" x-show="!recurso.id" @click="quitarRecurso(index)" class="rounded-md bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700">Quitar recurso nuevo</button>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label :for="`recurso-${recurso.key}-titulo`" class="block text-sm font-medium text-gray-700">Título</label>
                        <input type="text" :id="`recurso-${recurso.key}-titulo`" :name="`recursos[${index}][titulo]`" x-model="recurso.titulo" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label :for="`recurso-${recurso.key}-tipo`" class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select :id="`recurso-${recurso.key}-tipo`" :name="`recursos[${index}][tipo]`" x-model="recurso.tipo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($tiposRecurso as $tipoRecurso)
                                <option value="{{ $tipoRecurso->value }}">{{ $tipoRecurso->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label :for="`recurso-${recurso.key}-origen`" class="block text-sm font-medium text-gray-700">Origen</label>
                        <select :id="`recurso-${recurso.key}-origen`" :name="`recursos[${index}][origen]`" x-model="recurso.origen" @change="cambiarOrigen(recurso)" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($origenesRecurso as $origenRecurso)
                                <option value="{{ $origenRecurso->value }}">{{ $origenRecurso->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex w-full items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-medium text-gray-800">Estado del recurso</span>
                                <span class="text-gray-500" x-text="recurso.activo ? 'Activo' : 'Inactivo'"></span>
                            </span>
                            <input type="checkbox" :name="`recursos[${index}][activo]`" value="1" x-model="recurso.activo" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        </label>
                    </div>
                    <div x-show="recurso.origen === 'LOCAL'" class="md:col-span-2">
                        <label :for="`recurso-${recurso.key}-archivo`" class="block text-sm font-medium text-gray-700">Archivo local</label>
                        <input type="file" :id="`recurso-${recurso.key}-archivo`" :name="`recursos[${index}][archivo]`" :disabled="recurso.origen !== 'LOCAL'" class="mt-1 block w-full rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700">
                        <p x-show="recurso.id && recurso.tieneArchivoActual && recurso.origenOriginal === 'LOCAL'" class="mt-2 text-sm text-gray-500">Si no seleccionás otro archivo, se conservará el actual.</p>
                        <p x-show="necesitaArchivoNuevo(recurso)" class="mt-2 text-sm text-amber-700">Seleccioná un archivo para completar el recurso o el cambio de origen.</p>
                    </div>
                    <div x-show="recurso.origen === 'EXTERNO'" class="md:col-span-2">
                        <label :for="`recurso-${recurso.key}-url`" class="block text-sm font-medium text-gray-700">URL externa</label>
                        <input type="url" :id="`recurso-${recurso.key}-url`" :name="`recursos[${index}][url_externa]`" x-model="recurso.urlExterna" :disabled="recurso.origen !== 'EXTERNO'" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p x-show="recurso.id && recurso.origenOriginal === 'LOCAL'" class="mt-2 text-sm text-amber-700">Ingresá una URL válida para completar el cambio de origen.</p>
                    </div>
                </div>
            </article>
        </template>
    </div>
</section>
