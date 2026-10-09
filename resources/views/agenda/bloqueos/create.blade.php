@php
    $tieneRevision = is_array($revision);
    $inicioInicial = old('inicio_at', $revision['inicio_at'] ?? '');
    $finInicial = old('fin_at', $revision['fin_at'] ?? '');
    $motivoInicial = old('motivo', $revision['motivo'] ?? '');
    $mensajeStale = $mensajeRevisionObsoleta ?? null;
    $configuracionFormulario = [
        'tieneRevision' => $tieneRevision,
        'tieneErrores' => $errors->any(),
        'tieneMensajeStale' => $tieneRevision && filled($mensajeStale),
        'tieneDatosIniciales' => old('inicio_at') !== null || $tieneRevision,
        'inicioAt' => $inicioInicial,
        'finAt' => $finInicial,
        'motivo' => $motivoInicial,
        'urlCancelar' => route('turnos.agenda.index'),
        'cantidadTurnos' => $revision['cantidad_turnos_afectados'] ?? 0,
        'cantidadBloqueos' => $revision['cantidad_bloqueos_superpuestos'] ?? 0,
    ];
    $formatearFechaHora = fn (string $valor): string => \Carbon\CarbonImmutable::parse(
        $valor,
        config('app.timezone')
    )->setTimezone(config('app.timezone'))->format('d/m/Y H:i');
    $formatearInterseccion = function (array $bloqueoSuperpuesto) use ($revision): string {
        $inicioCandidato = \Carbon\CarbonImmutable::parse($revision['inicio_at'], config('app.timezone'));
        $finCandidato = \Carbon\CarbonImmutable::parse($revision['fin_at'], config('app.timezone'));
        $inicioExistente = \Carbon\CarbonImmutable::parse($bloqueoSuperpuesto['inicio_at'], config('app.timezone'));
        $finExistente = \Carbon\CarbonImmutable::parse($bloqueoSuperpuesto['fin_at'], config('app.timezone'));
        $inicioInterseccion = $inicioCandidato->greaterThan($inicioExistente) ? $inicioCandidato : $inicioExistente;
        $finInterseccion = $finCandidato->lessThan($finExistente) ? $finCandidato : $finExistente;

        if ($inicioInterseccion->isSameDay($finInterseccion)) {
            return $inicioInterseccion->format('d/m/Y H:i').' → '.$finInterseccion->format('H:i');
        }

        return $inicioInterseccion->format('d/m/Y H:i').' → '.$finInterseccion->format('d/m/Y H:i');
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Registrar bloqueo excepcional</h1>
            <p class="text-sm text-gray-500">Definí una indisponibilidad y revisá su impacto antes de registrarla.</p>
        </div>
    </x-slot>

    <style>[x-cloak] { display: none !important; }</style>

    <div class="py-8 sm:py-10">
        <div
            class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8"
            x-data="bloqueoAgendaForm(@js($configuracionFormulario))"
        >
            <aside class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-950">
                <p class="font-semibold">Antes de registrar se revisarán los impactos reales.</p>
                <p class="mt-1">El bloqueo impedirá ofrecer disponibilidad durante el intervalo. Los turnos ya otorgados no se reprograman ni se cancelan automáticamente.</p>
            </aside>

            @if ($errors->any())
                <div
                    id="resumen-errores-bloqueo"
                    class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500"
                    role="alert"
                    tabindex="-1"
                >
                    <p class="font-semibold">No pudimos revisar o registrar el bloqueo.</p>
                    <p class="mt-1">Corregí los datos indicados y generá una nueva revisión.</p>
                    <ul class="mt-3 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section x-show="etapa === 'captura'" x-cloak aria-labelledby="titulo-captura-bloqueo">
                <form
                    method="POST"
                    action="{{ route('turnos.agenda.bloqueos.review') }}"
                    class="space-y-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-7"
                    @submit="revisar($event)"
                >
                    @csrf

                    <div>
                        <h2 id="titulo-captura-bloqueo" class="text-xl font-semibold text-gray-900">Datos del bloqueo</h2>
                        <p class="mt-1 text-sm text-gray-600">Los campos marcados como obligatorios deben completarse. El inicio puede estar en el pasado si el bloqueo todavía está en curso.</p>
                    </div>

                    <div
                        id="resumen-validacion-bloqueo"
                        class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 focus:outline-none focus:ring-2 focus:ring-red-500"
                        role="alert"
                        tabindex="-1"
                        x-show="erroresLocales.length > 0"
                        x-cloak
                    >
                        <p class="font-semibold">Revisá el intervalo antes de continuar.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <template x-for="error in erroresLocales" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>

                    <fieldset id="selector-modo-bloqueo" tabindex="-1" aria-describedby="ayuda-modo-bloqueo">
                        <legend class="text-base font-semibold text-gray-900">Modo del intervalo</legend>
                        <p id="ayuda-modo-bloqueo" class="mt-1 text-sm text-gray-600">Elegí días completos o un intervalo continuo con horas específicas.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-xl border p-4 focus-within:ring-2 focus-within:ring-indigo-500" :class="modo === 'completo' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                                <input
                                    type="radio"
                                    value="completo"
                                    x-model="modo"
                                    @change="cambiarModo('completo')"
                                    class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span>
                                    <span class="block font-semibold text-gray-900">Todo el día / rango de días completos</span>
                                    <span class="mt-1 block text-sm text-gray-600">La fecha final visible se incluye completa.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-xl border p-4 focus-within:ring-2 focus-within:ring-indigo-500" :class="modo === 'parcial' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                                <input
                                    type="radio"
                                    value="parcial"
                                    x-model="modo"
                                    @change="cambiarModo('parcial')"
                                    class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span>
                                    <span class="block font-semibold text-gray-900">Intervalo parcial</span>
                                    <span class="mt-1 block text-sm text-gray-600">Puede abarcar varios días como un único intervalo continuo.</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="fecha-inicio-bloqueo" class="block text-sm font-medium text-gray-700">Fecha inicial <span class="text-red-700">(obligatorio)</span></label>
                            <input
                                id="fecha-inicio-bloqueo"
                                type="date"
                                required
                                x-model="fechaInicio"
                                @input="campoModificado()"
                                @class(['mt-1 block min-h-11 w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500', 'border-red-400' => $errors->has('inicio_at'), 'border-gray-300' => ! $errors->has('inicio_at')])
                                aria-describedby="ayuda-inicio-bloqueo{{ $errors->has('inicio_at') ? ' error-inicio-bloqueo' : '' }}"
                                aria-invalid="{{ $errors->has('inicio_at') ? 'true' : 'false' }}"
                            >
                            <p id="ayuda-inicio-bloqueo" class="mt-1 text-xs text-gray-500">Puede ser anterior a hoy si el intervalo finaliza en el futuro.</p>
                            @error('inicio_at')
                                <p id="error-inicio-bloqueo" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="modo === 'parcial'" x-cloak>
                            <label for="hora-inicio-bloqueo" class="block text-sm font-medium text-gray-700">Hora inicial <span class="text-red-700">(obligatorio)</span></label>
                            <input
                                id="hora-inicio-bloqueo"
                                type="time"
                                step="60"
                                :required="modo === 'parcial'"
                                x-model="horaInicio"
                                @input="campoModificado()"
                                class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                aria-describedby="ayuda-intervalo-continuo{{ $errors->has('inicio_at') ? ' error-inicio-bloqueo' : '' }}"
                                aria-invalid="{{ $errors->has('inicio_at') ? 'true' : 'false' }}"
                            >
                        </div>

                        <div>
                            <label for="fecha-fin-bloqueo" class="block text-sm font-medium text-gray-700">Fecha final <span class="text-red-700">(obligatorio)</span></label>
                            <input
                                id="fecha-fin-bloqueo"
                                type="date"
                                required
                                x-model="fechaFin"
                                @input="campoModificado()"
                                @class(['mt-1 block min-h-11 w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500', 'border-red-400' => $errors->has('fin_at'), 'border-gray-300' => ! $errors->has('fin_at')])
                                aria-describedby="ayuda-fin-bloqueo{{ $errors->has('fin_at') ? ' error-fin-bloqueo' : '' }}"
                                aria-invalid="{{ $errors->has('fin_at') ? 'true' : 'false' }}"
                            >
                            <p id="ayuda-fin-bloqueo" class="mt-1 text-xs text-gray-500" x-text="modo === 'completo' ? 'Esta fecha se incluye completa.' : 'Puede coincidir con la inicial si la hora final es posterior.'"></p>
                            @error('fin_at')
                                <p id="error-fin-bloqueo" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="modo === 'parcial'" x-cloak>
                            <label for="hora-fin-bloqueo" class="block text-sm font-medium text-gray-700">Hora final <span class="text-red-700">(obligatorio)</span></label>
                            <input
                                id="hora-fin-bloqueo"
                                type="time"
                                step="60"
                                :required="modo === 'parcial'"
                                x-model="horaFin"
                                @input="campoModificado()"
                                class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                aria-describedby="ayuda-intervalo-continuo{{ $errors->has('fin_at') ? ' error-fin-bloqueo' : '' }}"
                                aria-invalid="{{ $errors->has('fin_at') ? 'true' : 'false' }}"
                            >
                        </div>
                    </div>

                    <p id="ayuda-intervalo-continuo" class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900" x-show="modo === 'parcial'" x-cloak>
                        En varios días, el bloqueo cubre de forma continua todo el tiempo entre el inicio y la finalización; no se repite una franja por día.
                    </p>

                    <div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <label for="motivo" class="block text-sm font-medium text-gray-700">Motivo <span class="text-red-700">(obligatorio)</span></label>
                                <p id="ayuda-motivo" class="mt-1 text-xs text-gray-500">Texto libre, hasta 500 caracteres. Los botones son ayudas editables.</p>
                            </div>
                            <span class="text-xs text-gray-500"><span x-text="motivo.length"></span>/500</span>
                        </div>
                        <textarea
                            id="motivo"
                            name="motivo"
                            rows="4"
                            maxlength="500"
                            required
                            x-model="motivo"
                            @input="campoModificado()"
                            @class(['mt-2 block min-h-28 w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500', 'border-red-400' => $errors->has('motivo'), 'border-gray-300' => ! $errors->has('motivo')])
                            aria-describedby="ayuda-motivo{{ $errors->has('motivo') ? ' error-motivo' : '' }}"
                            aria-invalid="{{ $errors->has('motivo') ? 'true' : 'false' }}"
                        >{{ $motivoInicial }}</textarea>
                        @error('motivo')
                            <p id="error-motivo" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                        @enderror

                        <div class="mt-3 flex flex-wrap gap-2" aria-label="Motivos sugeridos">
                            @foreach (['Feriado', 'Vacaciones', 'Licencia', 'Congreso'] as $preset)
                                <button
                                    type="button"
                                    class="min-h-11 rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="aplicarPreset(@js($preset))"
                                >
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4" aria-live="polite">
                        <h3 class="font-semibold text-indigo-950">Resumen del intervalo</h3>
                        <p class="mt-1 text-sm text-indigo-900" x-text="resumenIntervalo"></p>
                    </div>

                    <input type="hidden" name="inicio_at" x-model="inicioAt">
                    <input type="hidden" name="fin_at" x-model="finAt">

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex min-h-11 justify-center rounded-lg bg-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                            @click="cancelar()"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600"
                            :disabled="enviando || !capturaCompleta"
                        >
                            Revisar bloqueo
                        </button>
                    </div>
                </form>
            </section>

            @if ($tieneRevision)
                <section x-show="etapa === 'revision'" x-cloak class="space-y-6" aria-labelledby="titulo-revision-bloqueo">
                    @if (filled($mensajeStale))
                        <div
                            id="mensaje-revision-obsoleta"
                            class="rounded-xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-600"
                            role="alert"
                            tabindex="-1"
                        >
                            <p class="font-semibold">La revisión anterior quedó desactualizada.</p>
                            <p class="mt-1">{{ $mensajeStale }}</p>
                        </div>
                    @endif

                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-7">
                        <h2 id="titulo-revision-bloqueo" class="text-xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500" tabindex="-1">Revisar bloqueo</h2>
                        <p class="mt-2 text-sm text-gray-600">El bloqueo todavía no fue registrado. Revisá el candidato y sus advertencias antes de confirmar.</p>

                        <dl class="mt-6 grid gap-5 rounded-xl bg-gray-50 p-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Inicio</dt>
                                <dd class="mt-1 font-semibold text-gray-900">{{ $formatearFechaHora($revision['inicio_at']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Finalización</dt>
                                <dd class="mt-1 font-semibold text-gray-900">{{ $formatearFechaHora($revision['fin_at']) }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Intervalo propuesto</dt>
                                <dd class="mt-1 font-semibold text-gray-900" x-text="resumenIntervalo"></dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Motivo</dt>
                                <dd class="mt-1 break-words whitespace-pre-line font-semibold text-gray-900">{{ $revision['motivo'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="turnos-afectados-titulo">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <h3 id="turnos-afectados-titulo" class="text-lg font-semibold text-gray-900">Turnos afectados</h3>
                            @if ($revision['cantidad_turnos_afectados'] > 0)
                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
                                    <p class="font-semibold">Hay {{ $revision['cantidad_turnos_afectados'] }} {{ $revision['cantidad_turnos_afectados'] === 1 ? 'turno otorgado' : 'turnos otorgados' }} dentro de este intervalo.</p>
                                    <p class="mt-1">Registrar el bloqueo no reprogramará ni cancelará estos turnos. Deberán gestionarse por separado.</p>
                                </div>
                            @else
                                <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">No se encontraron turnos otorgados dentro del intervalo.</p>
                            @endif
                        </div>

                        @if ($revision['cantidad_turnos_afectados'] > 0)
                            <div class="hidden sm:block">
                                <table class="w-full table-fixed text-left text-sm">
                                    <caption class="sr-only">Detalle cronológico de turnos afectados</caption>
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th scope="col" class="w-1/3 px-6 py-3">Fecha</th>
                                            <th scope="col" class="w-1/3 px-6 py-3">Horario</th>
                                            <th scope="col" class="w-1/3 px-6 py-3">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($revision['turnos_afectados'] as $turno)
                                            @php($estadoTurno = $turno['estado'] === 'CONFIRMADO' ? 'Confirmado' : 'Pendiente')
                                            <tr>
                                                <td class="px-6 py-4 font-medium text-gray-900">{{ \Carbon\CarbonImmutable::parse($turno['inicio_at'])->setTimezone(config('app.timezone'))->format('d/m/Y') }}</td>
                                                <td class="px-6 py-4 text-gray-700">{{ \Carbon\CarbonImmutable::parse($turno['inicio_at'])->setTimezone(config('app.timezone'))->format('H:i') }} a {{ \Carbon\CarbonImmutable::parse($turno['fin_at'])->setTimezone(config('app.timezone'))->format('H:i') }}</td>
                                                <td class="px-6 py-4"><span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">{{ $estadoTurno }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <ul class="divide-y divide-gray-100 sm:hidden" aria-label="Detalle de turnos afectados">
                                @foreach ($revision['turnos_afectados'] as $turno)
                                    @php($estadoTurno = $turno['estado'] === 'CONFIRMADO' ? 'Confirmado' : 'Pendiente')
                                    <li class="space-y-2 px-5 py-4">
                                        <p class="font-semibold text-gray-900">{{ \Carbon\CarbonImmutable::parse($turno['inicio_at'])->setTimezone(config('app.timezone'))->format('d/m/Y') }}</p>
                                        <p class="text-sm text-gray-700"><span class="font-medium">Horario:</span> {{ \Carbon\CarbonImmutable::parse($turno['inicio_at'])->setTimezone(config('app.timezone'))->format('H:i') }} a {{ \Carbon\CarbonImmutable::parse($turno['fin_at'])->setTimezone(config('app.timezone'))->format('H:i') }}</p>
                                        <p class="text-sm text-gray-700"><span class="font-medium">Estado:</span> {{ $estadoTurno }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="bloqueos-superpuestos-titulo">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <h3 id="bloqueos-superpuestos-titulo" class="text-lg font-semibold text-gray-900">Otros bloqueos superpuestos</h3>
                            @if ($revision['cantidad_bloqueos_superpuestos'] > 0)
                                <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950" role="status">
                                    <p class="font-semibold">El intervalo se superpone con {{ $revision['cantidad_bloqueos_superpuestos'] }} {{ $revision['cantidad_bloqueos_superpuestos'] === 1 ? 'bloqueo existente' : 'bloqueos existentes' }}.</p>
                                    <p class="mt-1">Podés continuar: los bloqueos se conservarán como registros independientes.</p>
                                </div>
                            @else
                                <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">No se superpone con otros bloqueos.</p>
                            @endif
                        </div>

                        @if ($revision['cantidad_bloqueos_superpuestos'] > 0)
                            <div class="hidden sm:block">
                                <table class="w-full table-fixed text-left text-sm">
                                    <caption class="sr-only">Detalle cronológico de bloqueos superpuestos</caption>
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th scope="col" class="w-1/4 px-6 py-3">Inicio</th>
                                            <th scope="col" class="w-1/4 px-6 py-3">Finalización</th>
                                            <th scope="col" class="w-1/4 px-6 py-3">Superposición</th>
                                            <th scope="col" class="w-1/4 px-6 py-3">Motivo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($revision['bloqueos_superpuestos'] as $bloqueoSuperpuesto)
                                            <tr>
                                                <td class="break-words px-6 py-4 font-medium text-gray-900">{{ $formatearFechaHora($bloqueoSuperpuesto['inicio_at']) }}</td>
                                                <td class="break-words px-6 py-4 text-gray-700">{{ $formatearFechaHora($bloqueoSuperpuesto['fin_at']) }}</td>
                                                <td class="break-words px-6 py-4 font-medium text-blue-900">{{ $formatearInterseccion($bloqueoSuperpuesto) }}</td>
                                                <td class="break-words px-6 py-4 text-gray-700">{{ $bloqueoSuperpuesto['motivo'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <ul class="divide-y divide-gray-100 sm:hidden" aria-label="Detalle de bloqueos superpuestos">
                                @foreach ($revision['bloqueos_superpuestos'] as $bloqueoSuperpuesto)
                                    <li class="space-y-2 px-5 py-4">
                                        <p class="font-semibold text-gray-900">{{ $bloqueoSuperpuesto['motivo'] }}</p>
                                        <p class="text-sm text-gray-700"><span class="font-medium">Desde:</span> {{ $formatearFechaHora($bloqueoSuperpuesto['inicio_at']) }}</p>
                                        <p class="text-sm text-gray-700"><span class="font-medium">Hasta:</span> {{ $formatearFechaHora($bloqueoSuperpuesto['fin_at']) }}</p>
                                        <p class="text-sm text-blue-900"><span class="font-medium">Superposición:</span> {{ $formatearInterseccion($bloqueoSuperpuesto) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <form
                        method="POST"
                        action="{{ route('turnos.agenda.bloqueos.store') }}"
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-7"
                        @submit="confirmarRegistro($event)"
                    >
                        @csrf
                        <input type="hidden" name="inicio_at" value="{{ $revision['inicio_at'] }}">
                        <input type="hidden" name="fin_at" value="{{ $revision['fin_at'] }}">
                        <input type="hidden" name="motivo" value="{{ $revision['motivo'] }}">
                        <input type="hidden" name="snapshot" value="{{ $revision['snapshot'] }}">

                        <p class="text-sm text-gray-600">Al confirmar se volverán a validar el intervalo, el snapshot y las advertencias. Si algo cambió, verás una nueva revisión antes de poder registrar.</p>
                        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                            <button
                                type="button"
                                class="inline-flex min-h-11 justify-center rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                                @click="cancelar()"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 justify-center rounded-lg bg-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                                @click="volverAEditar()"
                            >
                                Volver a editar
                            </button>
                            <button
                                type="submit"
                                class="inline-flex min-h-11 justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
                                :disabled="enviando"
                            >
                                Confirmar registro
                            </button>
                        </div>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
