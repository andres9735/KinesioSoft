@php
    $franjasPersistidas = $configuracion->franjasHorarias->map(fn ($franja): array => [
        'dia_semana' => $franja->dia_semana->value,
        'hora_inicio' => substr((string) $franja->hora_inicio, 0, 5),
        'hora_fin' => substr((string) $franja->hora_fin, 0, 5),
    ])->all();
    $franjasIniciales = old('franjas');

    if (! is_array($franjasIniciales)) {
        $franjasIniciales = $franjasPersistidas;
    }

    $franjasIniciales = array_values($franjasIniciales);
    $configuracionMatriz = [
        'franjas' => $franjasIniciales,
        'duracionTurnoMinutos' => old('duracion_turno_minutos', $configuracion->duracion_turno_minutos),
        'vigenciaDesde' => old('vigencia_desde', $configuracion->vigencia_desde->toDateString()),
        'vigenciaHasta' => old('vigencia_hasta', $configuracion->vigencia_hasta?->toDateString() ?? ''),
        'errores' => $errors->getMessages(),
        'tieneErroresServidor' => $errors->any(),
        'confirmacion' => [
            'titulo' => '¿Guardar cambios de la configuración futura?',
            'boton' => 'Confirmar cambios',
        ],
    ];
    $urlCancelar = match ($contextoRetorno['origen']) {
        'detalle' => route('turnos.agenda.configuraciones.show', $configuracion),
        'historial' => route('turnos.agenda.configuraciones.historial', array_filter([
            'page' => $contextoRetorno['page'] ?? null,
        ])),
        default => route('turnos.agenda.index'),
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Agenda de atención</p>
                <x-agenda.estado-configuracion-badge estado="FUTURA" />
            </div>
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Editar configuración futura</h1>
            <p class="text-sm text-gray-600">Los cambios se aplicarán sólo a esta vigencia, que todavía no comenzó.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form
                method="POST"
                action="{{ route('turnos.agenda.configuraciones.update', $configuracion) }}"
                class="space-y-6"
                x-data="matrizSemanalAgenda(@js($configuracionMatriz))"
                @input="marcarInteraccion()"
                @submit="confirmarEnvio($event)"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="snapshot" value="{{ $snapshot }}">
                <input type="hidden" name="origen" value="{{ $contextoRetorno['origen'] }}">
                @if (isset($contextoRetorno['page']))
                    <input type="hidden" name="page" value="{{ $contextoRetorno['page'] }}">
                @endif

                @if ($errors->any())
                    <div id="resumen-errores" tabindex="-1" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <h2 class="font-semibold">No pudimos actualizar la configuración</h2>
                        <p class="mt-1 text-sm">La matriz completa fue conservada. Revisá estos datos y confirmá nuevamente.</p>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="vigencia-configuracion">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                        <h2 id="vigencia-configuracion" class="text-lg font-semibold text-gray-900">Vigencia y duración</h2>
                        <p class="mt-1 text-sm text-gray-600">El período seguirá sujeto a la validación contra las vigencias vecinas.</p>
                    </div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <label for="vigencia_desde" class="block text-sm font-medium text-gray-700">Vigencia desde <span class="text-red-700">(obligatorio)</span></label>
                                <input id="vigencia_desde" name="vigencia_desde" type="date" required x-model="vigenciaDesde" aria-describedby="ayuda-vigencia-desde{{ $errors->has('vigencia_desde') ? ' error-vigencia-desde' : '' }}" aria-invalid="{{ $errors->has('vigencia_desde') ? 'true' : 'false' }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p id="ayuda-vigencia-desde" class="mt-2 text-sm text-gray-500">Debe continuar siendo una fecha posterior a hoy.</p>
                                @error('vigencia_desde')<p id="error-vigencia-desde" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="vigencia_hasta" class="block text-sm font-medium text-gray-700">Vigencia hasta <span class="text-gray-500">(opcional)</span></label>
                                <input id="vigencia_hasta" name="vigencia_hasta" type="date" x-model="vigenciaHasta" :min="vigenciaDesde || null" aria-describedby="ayuda-vigencia-hasta{{ $errors->has('vigencia_hasta') ? ' error-vigencia-hasta' : '' }}" aria-invalid="{{ $errors->has('vigencia_hasta') ? 'true' : 'false' }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p id="ayuda-vigencia-hasta" class="mt-2 text-sm text-gray-500">Dejala vacía para una vigencia sin fecha de fin.</p>
                                @error('vigencia_hasta')<p id="error-vigencia-hasta" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                                <p class="mt-2 text-sm font-medium text-red-700" x-show="!periodoValido">La fecha de finalización no puede ser anterior al inicio.</p>
                            </div>

                            <div>
                                <label for="duracion_turno_minutos" class="block text-sm font-medium text-gray-700">Duración estándar <span class="text-red-700">(obligatorio)</span></label>
                                <div class="relative mt-1">
                                    <input id="duracion_turno_minutos" name="duracion_turno_minutos" type="number" required min="15" max="180" step="5" x-model="duracionTurnoMinutos" aria-describedby="ayuda-duracion{{ $errors->has('duracion_turno_minutos') ? ' error-duracion' : '' }}" aria-invalid="{{ $errors->has('duracion_turno_minutos') ? 'true' : 'false' }}" class="block min-h-11 w-full rounded-lg border-gray-300 pr-20 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-500">minutos</span>
                                </div>
                                <p id="ayuda-duracion" class="mt-2 text-sm text-gray-500">Entre 15 y 180, en múltiplos de 5.</p>
                                @error('duracion_turno_minutos')<p id="error-duracion" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Cambiar el período puede reconciliar hacia adelante una configuración vecina. No modifica turnos otorgados ni configuraciones ya iniciadas.
                        </p>
                    </div>
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6">
                    <x-agenda.matriz-semanal-editable />
                </section>

                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-sm text-gray-500">El servidor volverá a comprobar que esta configuración siga siendo futura y no haya cambiado.</p>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $urlCancelar }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2" @click="confirmarCancelacion($event)">Cancelar</a>
                        <button type="submit" :disabled="tieneErroresLocales || enviando" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300">Guardar cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
