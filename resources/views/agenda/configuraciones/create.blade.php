@php
    $franjasIniciales = old('franjas');

    if (! is_array($franjasIniciales)) {
        $franjasIniciales = is_array($defaults['franjas'] ?? null) ? $defaults['franjas'] : [];
    }

    $franjasIniciales = array_values($franjasIniciales);
    $duracionInicial = old('duracion_turno_minutos', $defaults['duracion_turno_minutos'] ?? '');
    $configuracionMatriz = [
        'franjas' => $franjasIniciales,
        'duracionTurnoMinutos' => $duracionInicial,
        'vigenciaDesde' => old('vigencia_desde', ''),
        'vigenciaHasta' => old('vigencia_hasta', ''),
        'hoy' => $hoy->toDateString(),
        'permiteInicioHoy' => true,
        'esPrimeraConfiguracion' => $esPrimeraConfiguracion,
        'vigenciasExistentes' => $vigenciasExistentes,
        'errores' => $errors->getMessages(),
        'tieneErroresServidor' => $errors->any(),
        'confirmacion' => [
            'titulo' => '¿Guardar configuración de agenda?',
            'boton' => 'Confirmar guardado',
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Agenda de atención</p>
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">
                {{ $esPrimeraConfiguracion ? 'Crear primera configuración' : 'Nueva configuración de agenda' }}
            </h1>
            <p class="text-sm text-gray-600">Definí una vigencia, la duración estándar y los horarios habituales.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form
                method="POST"
                action="{{ route('turnos.agenda.configuraciones.store') }}"
                class="space-y-6"
                x-data="matrizSemanalAgenda(@js($configuracionMatriz))"
                @input="marcarInteraccion()"
                @submit="confirmarEnvio($event)"
            >
                @csrf

                @if ($errors->any())
                    <div id="resumen-errores" tabindex="-1" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <h2 class="font-semibold">No pudimos guardar la configuración</h2>
                        <p class="mt-1 text-sm">Revisá los siguientes datos y confirmá nuevamente.</p>
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
                        <p class="mt-1 text-sm text-gray-600">Los campos marcados como obligatorios deben completarse.</p>
                    </div>
                    <div class="space-y-5 p-5 sm:p-6">
                        @if ($esPrimeraConfiguracion)
                            <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                                Te sugerimos 45 minutos y atención de lunes a viernes de 08:00 a 17:00. Podés modificar o quitar cualquier franja antes de guardar.
                            </div>
                        @else
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                La nueva configuración comienza vacía. Agregá la matriz que querés programar; no se copiará otra vigencia de forma implícita.
                            </div>
                        @endif

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <label for="vigencia_desde" class="block text-sm font-medium text-gray-700">Vigencia desde <span class="text-red-700">(obligatorio)</span></label>
                                <input
                                    id="vigencia_desde"
                                    name="vigencia_desde"
                                    type="date"
                                    required
                                    min="{{ $hoy->toDateString() }}"
                                    x-model="vigenciaDesde"
                                    aria-describedby="ayuda-vigencia-desde{{ $errors->has('vigencia_desde') ? ' error-vigencia-desde' : '' }}"
                                    aria-invalid="{{ $errors->has('vigencia_desde') ? 'true' : 'false' }}"
                                    class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <p id="ayuda-vigencia-desde" class="mt-2 text-sm text-gray-500">
                                    {{ $esPrimeraConfiguracion
                                        ? 'Puede comenzar hoy porque será la primera configuración de la agenda.'
                                        : 'Debe ser posterior a hoy y respetar la continuidad de las vigencias existentes.' }}
                                </p>
                                @error('vigencia_desde')<p id="error-vigencia-desde" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="vigencia_hasta" class="block text-sm font-medium text-gray-700">Vigencia hasta <span class="text-gray-500">(opcional)</span></label>
                                <input
                                    id="vigencia_hasta"
                                    name="vigencia_hasta"
                                    type="date"
                                    x-model="vigenciaHasta"
                                    :min="vigenciaDesde || '{{ $hoy->toDateString() }}'"
                                    aria-describedby="ayuda-vigencia-hasta{{ $errors->has('vigencia_hasta') ? ' error-vigencia-hasta' : '' }}"
                                    aria-invalid="{{ $errors->has('vigencia_hasta') ? 'true' : 'false' }}"
                                    class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <p id="ayuda-vigencia-hasta" class="mt-2 text-sm text-gray-500">Dejala vacía si no querés definir una fecha de fin.</p>
                                @error('vigencia_hasta')<p id="error-vigencia-hasta" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                                <p class="mt-2 text-sm font-medium text-red-700" x-show="!periodoValido">La fecha de finalización no puede ser anterior al inicio.</p>
                            </div>

                            <div>
                                <label for="duracion_turno_minutos" class="block text-sm font-medium text-gray-700">Duración estándar <span class="text-red-700">(obligatorio)</span></label>
                                <div class="relative mt-1">
                                    <input
                                        id="duracion_turno_minutos"
                                        name="duracion_turno_minutos"
                                        type="number"
                                        required
                                        min="15"
                                        max="180"
                                        step="5"
                                        x-model="duracionTurnoMinutos"
                                        aria-describedby="ayuda-duracion{{ $errors->has('duracion_turno_minutos') ? ' error-duracion' : '' }}"
                                        aria-invalid="{{ $errors->has('duracion_turno_minutos') ? 'true' : 'false' }}"
                                        class="block min-h-11 w-full rounded-lg border-gray-300 pr-20 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-500">minutos</span>
                                </div>
                                <p id="ayuda-duracion" class="mt-2 text-sm text-gray-500">Entre 15 y 180, en múltiplos de 5.</p>
                                @error('duracion_turno_minutos')<p id="error-duracion" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div x-show="iniciaHoy" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                            Esta configuración comenzará hoy. Una vez guardada se considerará iniciada y ya no podrá editarse ni anularse como futura.
                        </div>

                        @if ($esPrimeraConfiguracion)
                            <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                Esta será la primera vigencia de tu agenda. Los horarios definidos se aplicarán desde la fecha indicada y no modificarán turnos ya otorgados.
                            </p>
                        @else
                            <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-700" aria-live="polite">
                                <p x-show="vistaPreviaReconciliacion" x-cloak x-text="vistaPreviaReconciliacion"></p>
                                <p x-show="!vistaPreviaReconciliacion">
                                    Guardar una nueva vigencia puede ajustar hacia adelante la fecha de fin de su predecesora. No modifica horarios históricos ni turnos ya otorgados.
                                </p>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6">
                    <x-agenda.matriz-semanal-editable />
                </section>

                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-sm text-gray-500">La configuración y sus franjas se registrarán juntas al confirmar.</p>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('turnos.agenda.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2" @click="confirmarCancelacion($event)">Cancelar</a>
                        <button type="submit" :disabled="tieneErroresLocales || enviando" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300">
                            Guardar configuración
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
