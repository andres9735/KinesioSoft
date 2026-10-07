@php
    $parametrosListado = array_filter([
        'filtro' => $contextoRetorno['filtro'],
        'bloqueos_page' => $contextoRetorno['bloqueos_page'] ?? null,
    ], fn ($valor): bool => $valor !== null);
    $rutaCancelar = $contextoRetorno['origen'] === 'detalle'
        ? route('turnos.agenda.bloqueos.show', [
            'bloqueo' => $bloqueo,
            'origen' => 'detalle',
            ...$parametrosListado,
        ])
        : route('turnos.agenda.index', $parametrosListado);
    $descripcionIntervalo = 'Desde '.$bloqueo->inicio_at->format('d/m/Y H:i')
        .' hasta '.$bloqueo->fin_at->format('d/m/Y H:i').'.';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Anular bloqueo</h1>
            <p class="text-sm text-gray-500">Revisá el registro y explicá por qué debe quedar anulado.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->has('bloqueo'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                    {{ $errors->first('bloqueo') }}
                </div>
            @endif

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="bloqueo-a-anular">
                <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <h2 id="bloqueo-a-anular" class="text-lg font-semibold text-gray-900">Bloqueo seleccionado</h2>
                    <x-agenda.estado-bloqueo-badge :estado="$estado" />
                </div>

                <dl class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Inicio del intervalo</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $bloqueo->inicio_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Fin del intervalo</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $bloqueo->fin_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Motivo original</dt>
                        <dd class="mt-1 break-words font-semibold text-gray-900">{{ $bloqueo->motivo }}</dd>
                    </div>
                    @if ($bloqueo->created_at !== null)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Fecha de registro</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $bloqueo->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="otros-bloqueos">
                <h2 id="otros-bloqueos" class="text-lg font-semibold text-gray-900">Otros bloqueos superpuestos</h2>
                <p class="mt-1 text-sm text-gray-600">Anular este registro no anula ningún otro bloqueo.</p>

                @if ($otrosBloqueosSuperpuestos->isEmpty())
                    <p class="mt-5 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm leading-6 text-gray-700">
                        No se encontraron otros bloqueos no anulados superpuestos. La disponibilidad se recalculará y también puede depender de la configuración de agenda, los turnos existentes y otras restricciones.
                    </p>
                @else
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        El intervalo puede continuar afectado por los siguientes bloqueos, que se conservarán sin cambios.
                    </div>
                    <ul class="mt-4 space-y-3">
                        @foreach ($otrosBloqueosSuperpuestos as $otroBloqueo)
                            <li class="rounded-xl border border-gray-200 p-4">
                                <p class="text-sm font-semibold text-gray-900">
                                    Desde {{ $otroBloqueo->inicio_at->format('d/m/Y H:i') }} hasta {{ $otroBloqueo->fin_at->format('d/m/Y H:i') }}
                                </p>
                                <p class="mt-1 break-words text-sm text-gray-700">{{ $otroBloqueo->motivo }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <form
                method="POST"
                action="{{ route('turnos.agenda.bloqueos.anular.store', $bloqueo) }}"
                class="js-confirm-action rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6"
                data-confirm-title="¿Confirmar anulación del bloqueo?"
                data-confirm-text="{{ $descripcionIntervalo }} La anulación conserva el registro y no modifica otros bloqueos ni turnos."
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
                <input type="hidden" name="filtro" value="{{ $contextoRetorno['filtro'] }}">
                @isset($contextoRetorno['bloqueos_page'])
                    <input type="hidden" name="bloqueos_page" value="{{ $contextoRetorno['bloqueos_page'] }}">
                @endisset

                <div>
                    <h2 id="motivo-anulacion-titulo" class="text-lg font-semibold text-gray-900">Motivo de la anulación</h2>
                    <p id="ayuda-motivo-anulacion" class="mt-1 text-sm text-gray-600">Este motivo quedará registrado para mantener la trazabilidad. Máximo 500 caracteres.</p>
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

                <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                    La anulación es lógica: el registro se conservará para consulta y esta operación no modifica turnos ya otorgados.
                </p>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ $rutaCancelar }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Cancelar</a>
                    <x-danger-button class="min-h-11 justify-center normal-case tracking-normal">Continuar con la anulación</x-danger-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
