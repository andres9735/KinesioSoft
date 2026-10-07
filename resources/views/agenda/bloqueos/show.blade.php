@php
    $parametrosVolver = array_filter([
        'filtro' => $contextoRetorno['filtro'],
        'bloqueos_page' => $contextoRetorno['bloqueos_page'] ?? null,
    ], fn ($valor): bool => $valor !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Consultar bloqueo</h1>
            <p class="text-sm text-gray-500">Datos originales, estado temporal y trazabilidad del bloqueo.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="datos-bloqueo">
                <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <h2 id="datos-bloqueo" class="text-lg font-semibold text-gray-900">Datos del bloqueo</h2>
                    <x-agenda.estado-bloqueo-badge :estado="$estado" />
                </div>
                <dl class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6">
                    <div><dt class="text-sm font-medium text-gray-500">Inicio del intervalo</dt><dd class="mt-1 font-semibold text-gray-900">{{ $bloqueo->inicio_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Fin del intervalo</dt><dd class="mt-1 font-semibold text-gray-900">{{ $bloqueo->fin_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-sm font-medium text-gray-500">Motivo original</dt><dd class="mt-1 break-words font-semibold text-gray-900">{{ $bloqueo->motivo }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Fecha de registro</dt><dd class="mt-1 font-semibold text-gray-900">{{ $bloqueo->created_at->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </section>

            @if ($bloqueo->anulado_at !== null)
                <section class="rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="anulacion-bloqueo">
                    <h2 id="anulacion-bloqueo" class="text-lg font-semibold text-red-900">Trazabilidad de la anulación</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm font-medium text-red-700">Anulado el</dt><dd class="mt-1 font-semibold text-red-900">{{ $bloqueo->anulado_at->format('d/m/Y H:i') }}</dd></div>
                        <div><dt class="text-sm font-medium text-red-700">Motivo</dt><dd class="mt-1 break-words font-semibold text-red-900">{{ $bloqueo->motivo_anulacion }}</dd></div>
                    </dl>
                </section>
            @endif

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="bloqueos-superpuestos">
                <h2 id="bloqueos-superpuestos" class="text-lg font-semibold text-gray-900">Otros bloqueos superpuestos</h2>
                <p class="mt-1 text-sm text-gray-500">Se muestran otros registros no anulados que intersectan el intervalo consultado.</p>

                @if ($otrosBloqueosSuperpuestos->isEmpty())
                    <p class="mt-5 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-600">No se encontraron otros bloqueos no anulados superpuestos.</p>
                @else
                    <ul class="mt-5 space-y-3">
                        @foreach ($otrosBloqueosSuperpuestos as $otroBloqueo)
                            <li class="rounded-xl border border-gray-200 p-4">
                                <p class="break-words font-semibold text-gray-900">{{ $otroBloqueo->motivo }}</p>
                                <p class="mt-1 text-sm text-gray-600">Desde {{ $otroBloqueo->inicio_at->format('d/m/Y H:i') }} hasta {{ $otroBloqueo->fin_at->format('d/m/Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <nav class="flex flex-col-reverse gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:justify-end" aria-label="Acciones del bloqueo">
                <a href="{{ route('turnos.agenda.index', $parametrosVolver) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Volver</a>
                @if ($puedeAnular)
                    <a href="{{ route('turnos.agenda.bloqueos.anular.create', ['bloqueo' => $bloqueo, 'origen' => 'detalle', ...$parametrosVolver]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Anular bloqueo</a>
                @endif
            </nav>
        </div>
    </div>
</x-app-layout>
