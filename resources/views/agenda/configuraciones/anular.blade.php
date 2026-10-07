@php
    $diasAtencion = $configuracion->franjasHorarias
        ->map(fn ($franja): int => $franja->dia_semana->value)
        ->unique()
        ->count();
    $parametrosHistorial = array_filter([
        'page' => $contextoRetorno['page'] ?? null,
    ], fn ($valor): bool => $valor !== null);
    $rutaCancelar = match ($contextoRetorno['origen']) {
        'detalle' => route('turnos.agenda.configuraciones.show', [
            'configuracion' => $configuracion,
            'origen' => 'detalle',
        ]),
        'historial' => route('turnos.agenda.configuraciones.historial', $parametrosHistorial),
        default => route('turnos.agenda.index'),
    };
    $descripcionPeriodo = 'Período desde '.$configuracion->vigencia_desde->format('d/m/Y')
        .' hasta '.($configuracion->vigencia_hasta?->format('d/m/Y') ?? 'sin fecha de fin').'.';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Anular configuración futura</h1>
            <p class="text-sm text-gray-500">Revisá la configuración, su continuidad prevista y el motivo de la anulación.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->has('agenda'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                    {{ $errors->first('agenda') }}
                </div>
            @endif

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="configuracion-a-anular">
                <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <h2 id="configuracion-a-anular" class="text-lg font-semibold text-gray-900">Configuración seleccionada</h2>
                    <x-agenda.estado-configuracion-badge :estado="$estado" />
                </div>

                <dl class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Vigencia desde</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->vigencia_desde->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Vigencia hasta</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->vigencia_hasta?->format('d/m/Y') ?? 'Sin fecha de fin' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Duración estándar</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->duracion_turno_minutos }} minutos</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Días con atención</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $diasAtencion }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Franjas semanales</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->franjasHorarias->count() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="matriz-a-anular">
                <h2 id="matriz-a-anular" class="text-lg font-semibold text-gray-900">Matriz semanal de sólo lectura</h2>
                <p class="mt-1 text-sm text-gray-600">Los horarios se muestran para identificar la configuración; no pueden editarse desde esta operación.</p>
                <x-agenda.matriz-semanal-lectura :franjas="$configuracion->franjasHorarias" id="anulacion-configuracion" class="mt-5" />
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="continuidad-prevista">
                <h2 id="continuidad-prevista" class="text-lg font-semibold text-gray-900">Vista previa de continuidad</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">Esta vista es informativa. La continuidad se revalidará y recalculará al confirmar.</p>

                <div class="mt-5 space-y-4">
                    @if ($predecesora !== null)
                        <article class="rounded-xl border border-gray-200 p-4">
                            <h3 class="font-semibold text-gray-900">Configuración anterior</h3>
                            <p class="mt-1 text-sm text-gray-700">
                                Desde {{ $predecesora->vigencia_desde->format('d/m/Y') }} hasta {{ $predecesora->vigencia_hasta?->format('d/m/Y') ?? 'sin fecha de fin' }}.
                            </p>

                            @if ($predecesoraVinculada && $sucesora !== null)
                                <p class="mt-3 text-sm leading-6 text-gray-700">
                                    Estaba vinculada con la configuración que se anulará. Se prevé que pueda extenderse hasta el {{ $vigenciaHastaProyectada->format('d/m/Y') }}, día previo al inicio de la siguiente configuración.
                                </p>
                            @elseif ($predecesoraVinculada)
                                <p class="mt-3 text-sm leading-6 text-gray-700">
                                    Estaba vinculada con la configuración que se anulará. Se prevé que pueda quedar nuevamente sin fecha de fin.
                                </p>
                            @else
                                <p class="mt-3 text-sm leading-6 text-gray-700">
                                    No estaba vinculada con la configuración que se anulará. El período sin configuración previo se conservará y las fechas de la configuración anterior no se modificarán.
                                </p>
                            @endif
                        </article>
                    @else
                        <article class="rounded-xl border border-gray-200 p-4">
                            <h3 class="font-semibold text-gray-900">Sin configuración anterior</h3>
                            <p class="mt-1 text-sm leading-6 text-gray-700">
                                Al anular esta configuración puede permanecer un período sin configuración hasta la siguiente vigencia, si existe.
                            </p>
                        </article>
                    @endif

                    @if ($sucesora !== null)
                        <article class="rounded-xl border border-gray-200 p-4">
                            <h3 class="font-semibold text-gray-900">Configuración siguiente</h3>
                            <p class="mt-1 text-sm leading-6 text-gray-700">
                                Su inicio el {{ $sucesora->vigencia_desde->format('d/m/Y') }} se conserva; esta operación no mueve ni reprograma esa vigencia.
                            </p>
                        </article>
                    @else
                        <article class="rounded-xl border border-gray-200 p-4">
                            <h3 class="font-semibold text-gray-900">Sin configuración siguiente</h3>
                            <p class="mt-1 text-sm leading-6 text-gray-700">No existe una vigencia posterior que deba mostrarse en esta vista previa.</p>
                        </article>
                    @endif
                </div>

                <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                    La anulación conservará esta configuración en el historial y no modificará automáticamente los turnos ya otorgados.
                </p>
            </section>

            <form
                method="POST"
                action="{{ route('turnos.agenda.configuraciones.anular.store', $configuracion) }}"
                class="js-confirm-action rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6"
                data-confirm-title="¿Confirmar anulación de la configuración?"
                data-confirm-text="{{ $descripcionPeriodo }} El registro se conservará, la continuidad prospectiva podrá reajustarse y los turnos existentes no se modificarán."
                data-confirm-button="Confirmar anulación"
                data-cancel-button="Volver"
                data-confirm-icon="warning"
                data-confirm-focus-cancel="true"
                aria-labelledby="motivo-anulacion-titulo"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="snapshot" value="{{ $snapshot }}">
                <input type="hidden" name="origen" value="{{ $contextoRetorno['origen'] }}">
                @isset($contextoRetorno['page'])
                    <input type="hidden" name="page" value="{{ $contextoRetorno['page'] }}">
                @endisset

                <div>
                    <h2 id="motivo-anulacion-titulo" class="text-lg font-semibold text-gray-900">Motivo de la anulación</h2>
                    <p id="ayuda-motivo-anulacion" class="mt-1 text-sm text-gray-600">Explicá por qué esta configuración futura ya no debe aplicarse. Máximo 500 caracteres.</p>
                </div>

                <div class="mt-5">
                    <label for="motivo_anulacion" class="block text-sm font-medium text-gray-700">Motivo de la anulación</label>
                    <textarea
                        id="motivo_anulacion"
                        name="motivo_anulacion"
                        rows="4"
                        maxlength="500"
                        required
                        aria-describedby="ayuda-motivo-anulacion error-motivo-anulacion"
                        aria-invalid="{{ $errors->has('motivo_anulacion') ? 'true' : 'false' }}"
                        class="mt-1 block min-h-28 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >{{ old('motivo_anulacion') }}</textarea>
                    <x-input-error id="error-motivo-anulacion" :messages="$errors->get('motivo_anulacion')" class="mt-2" />
                    <x-input-error :messages="$errors->get('snapshot')" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ $rutaCancelar }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Cancelar</a>
                    <x-danger-button class="min-h-11 justify-center normal-case tracking-normal">Continuar con la anulación</x-danger-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
