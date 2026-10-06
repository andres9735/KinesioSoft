@props([
    'mostrarCompatibilidad' => false,
])

@php
    $diasSemana = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
@endphp

<section {{ $attributes->class(['space-y-5']) }} aria-labelledby="matriz-semanal-titulo">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 id="matriz-semanal-titulo" class="text-lg font-semibold text-gray-900">Matriz semanal</h2>
            <p class="mt-1 text-sm text-gray-600">Agregá todas las franjas habituales. Los horarios contiguos son válidos.</p>
        </div>
        <p class="rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-800" aria-live="polite" x-text="resumenMatriz"></p>
    </div>

    @if ($errors->has('franjas'))
        <p id="error-franjas" class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first('franjas') }}
        </p>
    @endif

    <div
        id="resumen-validacion-local"
        tabindex="-1"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500"
        x-show="tieneErroresLocales"
        role="alert"
    >
        <p class="font-semibold">Revisá la matriz antes de guardar.</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <li x-show="cantidadFranjas === 0">Debe existir al menos una franja horaria.</li>
            <li x-show="!duracionValida">La duración debe ser un entero entre 15 y 180, múltiplo de 5.</li>
            <li x-show="!periodoValido">La fecha de finalización no puede ser anterior a la fecha de inicio.</li>
            <li x-show="franjas.some((franja) => erroresFranja(franja).length > 0)">Hay franjas incompletas, superpuestas o incompatibles con la duración.</li>
        </ul>
    </div>

    @if ($mostrarCompatibilidad)
        <div
            class="rounded-xl border px-4 py-3 text-sm"
            :class="cantidadIncompatibilidadesDuracion === 0 && duracionValida
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-amber-200 bg-amber-50 text-amber-900'"
            aria-live="polite"
        >
            <p class="font-semibold" x-text="mensajeCompatibilidad"></p>
            <p class="mt-1" x-show="cantidadIncompatibilidadesDuracion > 0">Corregí las filas identificadas antes de guardar la nueva duración.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5" x-show="cantidadIncompatibilidadesDuracion > 0">
                <template x-for="franja in incompatibilidadesDuracion" :key="'incompatible-' + franja.key">
                    <li>
                        <a
                            class="font-semibold underline underline-offset-2 focus:outline-none focus:ring-2 focus:ring-amber-600"
                            :href="'#' + idFila(franja)"
                            x-text="descripcionFranja(franja)"
                        ></a>
                    </li>
                </template>
            </ul>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($diasSemana as $numeroDia => $nombreDia)
            <fieldset class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <legend class="text-base font-semibold text-gray-900">{{ $nombreDia }}</legend>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="agregarFranja({{ $numeroDia }})"
                        aria-label="Agregar franja para {{ $nombreDia }}"
                    >
                        Agregar franja
                    </button>
                </div>

                <p class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500" x-show="franjasDia({{ $numeroDia }}).length === 0">
                    Sin atención
                </p>

                <div class="mt-4 space-y-4" x-show="franjasDia({{ $numeroDia }}).length > 0">
                    <template x-for="(franja, posicion) in franjasDia({{ $numeroDia }})" :key="franja.key">
                        <div
                            class="rounded-xl border p-4"
                            :class="filaInvalida(franja) ? 'border-red-300 bg-red-50/40' : 'border-gray-200 bg-gray-50/60'"
                            :id="idFila(franja)"
                            tabindex="-1"
                        >
                            <input
                                type="hidden"
                                :name="'franjas[' + indiceFranja(franja) + '][dia_semana]'"
                                value="{{ $numeroDia }}"
                            >

                            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" :for="idCampo(franja, 'inicio')">Hora de inicio</label>
                                    <input
                                        type="time"
                                        step="60"
                                        required
                                        class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :id="idCampo(franja, 'inicio')"
                                        :name="'franjas[' + indiceFranja(franja) + '][hora_inicio]'"
                                        x-model="franja.horaInicio"
                                        @input="limpiarErrorServidor(franja, 'hora_inicio')"
                                        :aria-describedby="idErroresFila(franja)"
                                        :aria-invalid="filaInvalida(franja).toString()"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700" :for="idCampo(franja, 'fin')">Hora de fin</label>
                                    <input
                                        type="time"
                                        step="60"
                                        required
                                        class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :id="idCampo(franja, 'fin')"
                                        :name="'franjas[' + indiceFranja(franja) + '][hora_fin]'"
                                        x-model="franja.horaFin"
                                        @input="limpiarErrorServidor(franja, 'hora_fin')"
                                        :aria-describedby="idErroresFila(franja)"
                                        :aria-invalid="filaInvalida(franja).toString()"
                                    >
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-inset ring-red-200 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    @click="quitarFranja(franja.key)"
                                    :aria-label="'Quitar franja de {{ $nombreDia }} ' + (posicion + 1)"
                                >
                                    Quitar franja
                                </button>
                            </div>

                            <div class="mt-3 text-sm text-gray-600">
                                <span x-show="duracionFranja(franja) !== null">
                                    Duración de la franja: <strong x-text="duracionFranja(franja) + ' min'"></strong>
                                </span>
                            </div>

                            <div :id="idErroresFila(franja)" class="mt-2 space-y-1 text-sm font-medium text-red-700" aria-live="polite">
                                <p x-show="errorCampoServidor(franja, 'fila')" x-text="errorCampoServidor(franja, 'fila')"></p>
                                <p x-show="errorCampoServidor(franja, 'dia_semana')" x-text="errorCampoServidor(franja, 'dia_semana')"></p>
                                <p x-show="errorCampoServidor(franja, 'hora_inicio')" x-text="errorCampoServidor(franja, 'hora_inicio')"></p>
                                <p x-show="errorCampoServidor(franja, 'hora_fin')" x-text="errorCampoServidor(franja, 'hora_fin')"></p>
                                <template x-for="error in erroresFranja(franja)" :key="error">
                                    <p x-text="error"></p>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </fieldset>
        @endforeach
    </div>
</section>
