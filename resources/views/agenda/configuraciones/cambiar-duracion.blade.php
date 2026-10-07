@php
    $franjasOrigen = $configuracionOrigen->franjasHorarias->map(fn ($franja): array => [
        'dia_semana' => $franja->dia_semana->value,
        'hora_inicio' => substr((string) $franja->hora_inicio, 0, 5),
        'hora_fin' => substr((string) $franja->hora_fin, 0, 5),
    ])->all();
    $franjasIniciales = old('franjas');

    if (! is_array($franjasIniciales)) {
        $franjasIniciales = $franjasOrigen;
    }

    $franjasIniciales = array_values($franjasIniciales);
    $configuracionMatriz = [
        'franjas' => $franjasIniciales,
        'duracionTurnoMinutos' => old('duracion_turno_minutos', $duracionActual),
        'vigenciaDesde' => old('vigencia_desde', $vigenciaDesde->toDateString()),
        'vigenciaHastaDerivada' => $vigenciaHastaDerivada?->toDateString() ?? '',
        'errores' => $errors->getMessages(),
        'tieneErroresServidor' => $errors->any(),
        'confirmacion' => [
            'titulo' => '¿Programar la nueva duración?',
            'boton' => 'Guardar nueva duración',
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Agenda de atención</p>
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Cambiar duración estándar</h1>
            <p class="text-sm text-gray-600">Programá una nueva vigencia sin modificar retrospectivamente la configuración de origen.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form
                method="POST"
                action="{{ route('turnos.agenda.duracion.store') }}"
                class="space-y-6"
                x-data="matrizSemanalAgenda(@js($configuracionMatriz))"
                @input="marcarCambios()"
                @submit="confirmarEnvio($event)"
            >
                @csrf
                <input type="hidden" name="snapshot" value="{{ $snapshot }}">

                @if ($errors->any())
                    <div id="resumen-errores" tabindex="-1" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <h2 class="font-semibold">No pudimos programar la nueva duración</h2>
                        <p class="mt-1 text-sm">La matriz ingresada fue conservada. Revisá estos datos y confirmá nuevamente.</p>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="comparacion-duracion">
                    <h2 id="comparacion-duracion" class="text-lg font-semibold text-gray-900">Resumen del cambio</h2>
                    <p class="mt-1 text-sm text-gray-600">La matriz es una copia editable de la configuración aplicable al día anterior.</p>
                    <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl bg-gray-50 p-4">
                            <dt class="text-sm text-gray-500">Duración actual</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $duracionActual }} minutos</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <dt class="text-sm text-gray-500">Configuración de origen</dt>
                            <dd class="mt-1 font-semibold text-gray-900">Desde {{ $configuracionOrigen->vigencia_desde->format('d/m/Y') }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <dt class="text-sm text-gray-500">Fin de la nueva vigencia</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $vigenciaHastaDerivada?->format('d/m/Y') ?? 'Sin fecha de fin' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Se creará una configuración nueva y se preservará el histórico. Los turnos existentes no se modifican automáticamente.
                    </p>
                </section>

                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="datos-nueva-duracion">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                        <h2 id="datos-nueva-duracion" class="text-lg font-semibold text-gray-900">Nueva duración y fecha de efecto</h2>
                    </div>
                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="duracion_turno_minutos" class="block text-sm font-medium text-gray-700">Nueva duración estándar <span class="text-red-700">(obligatorio)</span></label>
                            <div class="relative mt-1">
                                <input id="duracion_turno_minutos" name="duracion_turno_minutos" type="number" required min="15" max="180" step="5" x-model="duracionTurnoMinutos" aria-describedby="ayuda-duracion{{ $errors->has('duracion_turno_minutos') ? ' error-duracion' : '' }}" aria-invalid="{{ $errors->has('duracion_turno_minutos') ? 'true' : 'false' }}" class="block min-h-11 w-full rounded-lg border-gray-300 pr-20 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-500">minutos</span>
                            </div>
                            <p id="ayuda-duracion" class="mt-2 text-sm text-gray-500">Entre 15 y 180, en múltiplos de 5. La matriz se revalida al instante.</p>
                            @error('duracion_turno_minutos')<p id="error-duracion" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="vigencia_desde" class="block text-sm font-medium text-gray-700">Vigencia desde <span class="text-red-700">(obligatorio)</span></label>
                            <input id="vigencia_desde" name="vigencia_desde" type="date" required min="{{ $fechaMinima->toDateString() }}" x-model="vigenciaDesde" aria-describedby="ayuda-vigencia-desde{{ $errors->has('vigencia_desde') ? ' error-vigencia-desde' : '' }}" aria-invalid="{{ $errors->has('vigencia_desde') ? 'true' : 'false' }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p id="ayuda-vigencia-desde" class="mt-2 text-sm text-gray-500">Debe ser posterior a hoy. La fecha de fin, si existe una sucesora, se calcula en el servidor.</p>
                            @error('vigencia_desde')<p id="error-vigencia-desde" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6">
                    <x-agenda.matriz-semanal-editable :mostrar-compatibilidad="true" />
                </section>

                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-sm text-gray-500">La configuración de origen se vuelve a comprobar antes de escribir.</p>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('turnos.agenda.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2" @click="confirmarCancelacion($event)">Cancelar</a>
                        <button type="submit" :disabled="tieneErroresLocales || enviando" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300">Guardar nueva duración</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
