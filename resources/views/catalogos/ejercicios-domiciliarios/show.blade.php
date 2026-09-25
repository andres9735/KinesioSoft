<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">Consultar ejercicio domiciliario</h2>
            <p class="text-sm text-gray-500">Vista completa de sólo lectura del ejercicio seleccionado.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Datos generales</h3></div>
                <dl class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-sm font-medium text-gray-500">Código</dt><dd class="mt-1 break-words font-semibold text-gray-900">{{ $ejercicioDomiciliario->codigo }}</dd></div>
                    <div class="lg:col-span-2"><dt class="text-sm font-medium text-gray-500">Nombre</dt><dd class="mt-1 break-words font-semibold text-gray-900">{{ $ejercicioDomiciliario->nombre }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-sm font-medium text-gray-500">Descripción</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $ejercicioDomiciliario->descripcion }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Dificultad</dt><dd class="mt-1 text-gray-900">{{ $ejercicioDomiciliario->dificultad_base->value }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Estado del ejercicio</dt><dd class="mt-1"><x-catalogos.estado-badge :activo="$ejercicioDomiciliario->activo" class="inline-flex items-center" /></dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Parámetros recomendados</h3></div>
                <dl class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-sm font-medium text-gray-500">Series recomendadas</dt><dd class="mt-1 text-gray-900">{{ $ejercicioDomiciliario->series_recomendadas ?? 'No informado' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Repeticiones recomendadas</dt><dd class="mt-1 text-gray-900">{{ $ejercicioDomiciliario->repeticiones_recomendadas ?? 'No informado' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Duración recomendada</dt><dd class="mt-1 text-gray-900">{{ $ejercicioDomiciliario->duracion_segundos_recomendada !== null ? $ejercicioDomiciliario->duracion_segundos_recomendada.' segundos' : 'No informada' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Frecuencia base</dt><dd class="mt-1 text-gray-900">{{ $ejercicioDomiciliario->frecuencia_cantidad_base !== null ? $ejercicioDomiciliario->frecuencia_cantidad_base.' · '.$ejercicioDomiciliario->frecuencia_periodo_base->value : 'No informada' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Descanso</dt><dd class="mt-1 text-gray-900">{{ $ejercicioDomiciliario->descanso_segundos_base !== null ? $ejercicioDomiciliario->descanso_segundos_base.' segundos' : 'No informado' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Carga / resistencia</dt><dd class="mt-1 break-words text-gray-900">{{ $ejercicioDomiciliario->carga_resistencia_sugerida ?: 'No informada' }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Contraindicaciones / notas</h3></div>
                <dl class="grid gap-6 p-6 lg:grid-cols-2">
                    <div><dt class="text-sm font-medium text-gray-500">Contraindicaciones</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $ejercicioDomiciliario->contraindicaciones ?: 'Sin contraindicaciones informadas' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Notas clínicas</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-900">{{ $ejercicioDomiciliario->notas_clinicas ?: 'Sin notas clínicas' }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Categorías asociadas</h3></div>
                <div class="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($ejercicioDomiciliario->categorias as $categoria)
                        <article class="rounded-xl border border-gray-200 p-4">
                            <p class="break-words font-semibold text-gray-900">{{ $categoria->codigo }} · {{ $categoria->nombre }}</p>
                            <p class="mt-2 text-sm text-gray-600">Tipo: {{ $categoria->tipo->value }}</p>
                            <div class="mt-2"><x-catalogos.estado-badge :activo="$categoria->activo" class="inline-flex items-center" /></div>
                        </article>
                    @empty
                        <p class="text-sm text-gray-500">Sin categorías asociadas.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="border-b bg-gray-50 px-6 py-4"><h3 class="text-lg font-semibold text-gray-800">Recursos asociados</h3></div>
                <div class="grid gap-4 p-6 lg:grid-cols-2">
                    @forelse ($ejercicioDomiciliario->recursos as $recurso)
                        <article class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h4 class="break-words font-semibold text-gray-900">{{ $recurso->titulo }}</h4>
                                    <p class="mt-1 text-sm text-gray-600">{{ $recurso->tipo->value }} · {{ $recurso->origen->value }} · Orden {{ $recurso->orden }}</p>
                                </div>
                                <x-catalogos.estado-badge :activo="$recurso->activo" class="shrink-0" />
                            </div>
                            @if ($recurso->origen->value === 'EXTERNO')
                                <a href="{{ $recurso->url_externa }}" target="_blank" rel="noopener noreferrer" class="mt-3 block break-all text-sm font-medium text-indigo-700 hover:underline">{{ $recurso->url_externa }}</a>
                            @else
                                <p class="mt-3 break-words text-sm text-gray-600">Archivo local: {{ basename(str_replace('\\', '/', $recurso->ruta_storage)) }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-gray-500">Sin recursos asociados.</p>
                    @endforelse
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                <a href="{{ route('catalogos.ejercicios.index') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-300">Volver</a>
                @can('update', $ejercicioDomiciliario)
                    <a href="{{ route('catalogos.ejercicios.edit', $ejercicioDomiciliario) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700">Editar</a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
