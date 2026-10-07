@php
    $diasAtencion = $configuracion->franjasHorarias
        ->map(fn ($franja): int => $franja->dia_semana->value)
        ->unique()
        ->count();
    $parametrosVolver = $contextoRetorno['origen'] === 'historial'
        ? array_filter(['page' => $contextoRetorno['page'] ?? null], fn ($valor): bool => $valor !== null)
        : [];
    $rutaVolver = $contextoRetorno['origen'] === 'historial'
        ? route('turnos.agenda.configuraciones.historial', $parametrosVolver)
        : route('turnos.agenda.index');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Consultar configuración de agenda</h1>
            <p class="text-sm text-gray-500">Detalle completo en modo de sólo lectura.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="datos-configuracion">
                <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <h2 id="datos-configuracion" class="text-lg font-semibold text-gray-900">Datos de la configuración</h2>
                    <x-agenda.estado-configuracion-badge :estado="$estado" :proxima="$esProxima" />
                </div>
                <dl class="grid gap-6 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div><dt class="text-sm font-medium text-gray-500">Vigencia desde</dt><dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->vigencia_desde->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Vigencia hasta</dt><dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->vigencia_hasta?->format('d/m/Y') ?? 'Sin fecha de fin' }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Duración estándar</dt><dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->duracion_turno_minutos }} minutos</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Días con atención</dt><dd class="mt-1 font-semibold text-gray-900">{{ $diasAtencion }}</dd></div>
                    <div><dt class="text-sm font-medium text-gray-500">Franjas semanales</dt><dd class="mt-1 font-semibold text-gray-900">{{ $configuracion->franjasHorarias->count() }}</dd></div>
                </dl>
            </section>

            @if ($estado === 'HISTORICA')
                <aside class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-700">Esta configuración es histórica y se conserva en modo de sólo lectura.</aside>
            @elseif ($estado === 'ANULADA')
                <section class="rounded-2xl border border-red-200 bg-red-50 p-5" aria-labelledby="trazabilidad-anulacion">
                    <h2 id="trazabilidad-anulacion" class="text-lg font-semibold text-red-900">Trazabilidad de la anulación</h2>
                    <p class="mt-2 text-sm text-red-800">La configuración se conserva para consulta y ya no participa en la disponibilidad ni en solapamientos efectivos.</p>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm font-medium text-red-700">Anulada el</dt><dd class="mt-1 text-sm font-semibold text-red-900">{{ $configuracion->anulado_at->format('d/m/Y H:i') }}</dd></div>
                        <div><dt class="text-sm font-medium text-red-700">Motivo</dt><dd class="mt-1 break-words text-sm font-semibold text-red-900">{{ $configuracion->motivo_anulacion }}</dd></div>
                    </dl>
                </section>
            @endif

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="matriz-configuracion">
                <h2 id="matriz-configuracion" class="mb-4 text-lg font-semibold text-gray-900">Matriz semanal</h2>
                <x-agenda.matriz-semanal-lectura :franjas="$configuracion->franjasHorarias" id="detalle-configuracion" />
            </section>

            <nav class="flex flex-col-reverse gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:justify-end" aria-label="Acciones de la configuración">
                <a href="{{ $rutaVolver }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Volver</a>
                @if ($puedeEditar)
                    <a href="{{ route('turnos.agenda.configuraciones.edit', ['configuracion' => $configuracion, 'origen' => 'detalle']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Editar</a>
                @endif
                @if ($puedeAnular)
                    <a href="{{ route('turnos.agenda.configuraciones.anular.create', ['configuracion' => $configuracion, 'origen' => 'detalle']) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Anular</a>
                @endif
            </nav>
        </div>
    </div>
</x-app-layout>
