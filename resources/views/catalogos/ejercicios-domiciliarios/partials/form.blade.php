@php
    $esEdicion = isset($ejercicioDomiciliario) && $ejercicioDomiciliario;
    $idsCategoriasActuales = $esEdicion ? $ejercicioDomiciliario->categorias->modelKeys() : [];
    $idsCategoriasSeleccionadas = collect(old('categorias', $idsCategoriasActuales))->map(fn ($id) => (int) $id);
    $tiposCategorias = ['ANATOMICA', 'FUNCIONAL', 'TERAPEUTICA', 'EQUIPAMIENTO'];
@endphp

<section aria-labelledby="datos-generales" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
    <div class="border-b bg-gray-50 px-6 py-4">
        <h3 id="datos-generales" class="text-lg font-semibold text-gray-800">1. Datos generales</h3>
    </div>
    <div class="grid gap-6 p-6 sm:grid-cols-2">
        <div>
            <x-input-label for="codigo" value="Código" />
            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo', $esEdicion ? $ejercicioDomiciliario->codigo : '')" maxlength="255" required />
            <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="nombre" value="Nombre" />
            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $esEdicion ? $ejercicioDomiciliario->nombre : '')" maxlength="255" required />
            <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
        </div>
        <div class="sm:col-span-2">
            <x-input-label for="descripcion" value="Descripción" />
            <textarea id="descripcion" name="descripcion" rows="5" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion', $esEdicion ? $ejercicioDomiciliario->descripcion : '') }}</textarea>
            <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="dificultad_base" value="Dificultad base" />
            <select id="dificultad_base" name="dificultad_base" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Seleccione una dificultad</option>
                @foreach ($dificultades as $dificultad)
                    <option value="{{ $dificultad->value }}" @selected(old('dificultad_base', $esEdicion ? $ejercicioDomiciliario->dificultad_base->value : '') === $dificultad->value)>{{ $dificultad->value }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('dificultad_base')" class="mt-2" />
        </div>
    </div>
</section>

<section aria-labelledby="parametros-recomendados" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
    <div class="border-b bg-gray-50 px-6 py-4">
        <h3 id="parametros-recomendados" class="text-lg font-semibold text-gray-800">2. Parámetros recomendados</h3>
    </div>
    <div class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <x-input-label for="series_recomendadas" value="Series recomendadas" />
            <x-text-input id="series_recomendadas" name="series_recomendadas" type="number" min="1" class="mt-1 block w-full" :value="old('series_recomendadas', $esEdicion ? $ejercicioDomiciliario->series_recomendadas : '')" />
            <x-input-error :messages="$errors->get('series_recomendadas')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="repeticiones_recomendadas" value="Repeticiones recomendadas" />
            <x-text-input id="repeticiones_recomendadas" name="repeticiones_recomendadas" type="number" min="1" class="mt-1 block w-full" :value="old('repeticiones_recomendadas', $esEdicion ? $ejercicioDomiciliario->repeticiones_recomendadas : '')" />
            <x-input-error :messages="$errors->get('repeticiones_recomendadas')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="duracion_segundos_recomendada" value="Duración recomendada (segundos)" />
            <x-text-input id="duracion_segundos_recomendada" name="duracion_segundos_recomendada" type="number" min="1" class="mt-1 block w-full" :value="old('duracion_segundos_recomendada', $esEdicion ? $ejercicioDomiciliario->duracion_segundos_recomendada : '')" />
            <x-input-error :messages="$errors->get('duracion_segundos_recomendada')" class="mt-2" />
        </div>
        <div class="sm:col-span-2 lg:col-span-2">
            <span class="block text-sm font-medium text-gray-700">Frecuencia base</span>
            <div class="mt-1 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="frecuencia_cantidad_base" class="sr-only">Cantidad de frecuencia base</label>
                    <x-text-input id="frecuencia_cantidad_base" name="frecuencia_cantidad_base" type="number" min="1" class="block w-full" placeholder="Cantidad" :value="old('frecuencia_cantidad_base', $esEdicion ? $ejercicioDomiciliario->frecuencia_cantidad_base : '')" />
                </div>
                <div>
                    <label for="frecuencia_periodo_base" class="sr-only">Período de frecuencia base</label>
                    <select id="frecuencia_periodo_base" name="frecuencia_periodo_base" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Seleccione período</option>
                        @foreach ($periodosFrecuencia as $periodo)
                            <option value="{{ $periodo->value }}" @selected(old('frecuencia_periodo_base', $esEdicion ? $ejercicioDomiciliario->frecuencia_periodo_base?->value : '') === $periodo->value)>{{ $periodo->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <x-input-error :messages="$errors->get('frecuencia_cantidad_base')" class="mt-2" />
            <x-input-error :messages="$errors->get('frecuencia_periodo_base')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="descanso_segundos_base" value="Descanso base (segundos)" />
            <x-text-input id="descanso_segundos_base" name="descanso_segundos_base" type="number" min="0" class="mt-1 block w-full" :value="old('descanso_segundos_base', $esEdicion ? $ejercicioDomiciliario->descanso_segundos_base : '')" />
            <x-input-error :messages="$errors->get('descanso_segundos_base')" class="mt-2" />
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
            <x-input-label for="carga_resistencia_sugerida" value="Carga o resistencia sugerida" />
            <x-text-input id="carga_resistencia_sugerida" name="carga_resistencia_sugerida" type="text" class="mt-1 block w-full" :value="old('carga_resistencia_sugerida', $esEdicion ? $ejercicioDomiciliario->carga_resistencia_sugerida : '')" maxlength="255" />
            <x-input-error :messages="$errors->get('carga_resistencia_sugerida')" class="mt-2" />
        </div>
    </div>
</section>

<section aria-labelledby="clasificacion-clinica" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
    <div class="border-b bg-gray-50 px-6 py-4">
        <h3 id="clasificacion-clinica" class="text-lg font-semibold text-gray-800">3. Clasificación clínica</h3>
        <p class="mt-1 text-sm text-gray-500">Seleccione categorías anatómicas, funcionales o terapéuticas.</p>
    </div>
    <div class="space-y-5 p-6">
        @foreach ($tiposCategorias as $tipoCategoria)
            @php $categoriasDelTipo = $categoriasDisponibles->where('tipo.value', $tipoCategoria); @endphp
            @if ($categoriasDelTipo->isNotEmpty())
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800">{{ $tipoCategoria }}</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($categoriasDelTipo as $categoria)
                            @php
                                $esActual = in_array($categoria->id, $idsCategoriasActuales, true);
                                $esHistorica = $esActual && (! $categoria->activo || $categoria->tipo->value === 'EQUIPAMIENTO');
                            @endphp
                            <label class="flex items-start gap-3 rounded-xl border p-3 {{ $esHistorica ? 'border-amber-300 bg-amber-50' : 'border-gray-200' }}">
                                <input type="checkbox" name="categorias[]" value="{{ $categoria->id }}" @checked($idsCategoriasSeleccionadas->contains($categoria->id)) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="min-w-0 text-sm">
                                    <span class="block break-words font-medium text-gray-900">{{ $categoria->codigo }} · {{ $categoria->nombre }}</span>
                                    <span class="block text-gray-500">{{ $categoria->tipo->value }} · {{ $categoria->activo ? 'Activa' : 'Inactiva' }}</span>
                                    @if ($esHistorica)
                                        <span class="mt-1 block font-medium text-amber-700">Histórica / no disponible para nuevas asociaciones</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        @endforeach
        @if ($categoriasDisponibles->isEmpty())
            <p class="text-sm text-gray-500">No hay categorías disponibles.</p>
        @endif
        <x-input-error :messages="$errors->get('categorias')" class="mt-2" />
        @foreach ($errors->get('categorias.*') as $mensajes)
            <x-input-error :messages="$mensajes" class="mt-2" />
        @endforeach
        <p class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
            {{ $esEdicion && $ejercicioDomiciliario->activo ? 'Debe conservar al menos una categoría ANATOMICA activa.' : 'El ejercicio activo requiere al menos una categoría ANATOMICA activa.' }}
        </p>
    </div>
</section>

<section aria-labelledby="contraindicaciones-notas" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
    <div class="border-b bg-gray-50 px-6 py-4">
        <h3 id="contraindicaciones-notas" class="text-lg font-semibold text-gray-800">4. Contraindicaciones / notas</h3>
    </div>
    <div class="grid gap-6 p-6 lg:grid-cols-2">
        <div>
            <x-input-label for="contraindicaciones" value="Contraindicaciones" />
            <textarea id="contraindicaciones" name="contraindicaciones" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('contraindicaciones', $esEdicion ? $ejercicioDomiciliario->contraindicaciones : '') }}</textarea>
            <x-input-error :messages="$errors->get('contraindicaciones')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="notas_clinicas" value="Notas clínicas" />
            <textarea id="notas_clinicas" name="notas_clinicas" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notas_clinicas', $esEdicion ? $ejercicioDomiciliario->notas_clinicas : '') }}</textarea>
            <x-input-error :messages="$errors->get('notas_clinicas')" class="mt-2" />
        </div>
    </div>
</section>

@include('catalogos.ejercicios-domiciliarios.partials.recursos')
