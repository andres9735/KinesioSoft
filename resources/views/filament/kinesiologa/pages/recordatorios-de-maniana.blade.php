<x-filament-panels::page>
    @php
        $isDisabled = ($total === 0);
        $variantClasses = $simulate
            ? 'bg-amber-500 hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-700'
            : 'bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700';
    @endphp

    <div x-data class="space-y-4">

        {{-- Encabezado y controles --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Recordatorios de mañana</h1>
                <p class="text-sm text-gray-500">
                    Elegí el <strong>día a notificar</strong> y previsualizá antes de enviar.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <label class="text-sm">
                    <span class="mb-1 block text-gray-600 dark:text-gray-300">Fecha objetivo</span>
                    <input
                        type="date"
                        wire:model.live="fecha"
                        class="fi-input block rounded-lg border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                               focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30
                               dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>

                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="simulate"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    <span>Simular (no envía correos, marca <code>simulated</code>)</span>
                </label>

                <div class="hidden h-6 w-px bg-gray-200 dark:bg-gray-700 sm:block"></div>

                {{-- Previsualizar --}}
                <button
                    type="button"
                    wire:click="preview"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium
                           text-gray-900 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500/30
                           dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
                >
                    <span wire:loading.remove wire:target="preview">Previsualizar</span>
                    <span wire:loading wire:target="preview">Cargando…</span>
                </button>

                {{-- Enviar ahora (colores siempre aplicados) --}}
                <button
                    type="button"
                    x-on:click="
                        if ({{ $isDisabled ? 'true' : 'false' }}) return;
                        if (confirm('{{ $simulate ? '¿Ejecutar en modo SIMULADO?' : '¿Enviar correos ahora (REAL)?' }}')) { $wire.run() }
                    "
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-white
                           focus:outline-none focus:ring-2 focus:ring-blue-500/30
                           {{ $variantClasses }} {{ $isDisabled ? 'opacity-70 cursor-not-allowed' : '' }}"
                    @disabled($isDisabled)
                >
                    <span wire:loading.remove wire:target="run">
                        Enviar ahora ({{ $simulate ? 'simulado' : 'real' }})
                    </span>
                    <span wire:loading wire:target="run">Procesando…</span>
                </button>
            </div>
        </div>

        {{-- Resumen compacto (chip) --}}
        <div class="inline-flex w-fit items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm
                    dark:border-gray-800 dark:bg-gray-900">
            <span class="font-medium">Total a procesar:</span>
            <span>{{ $total }}</span>
        </div>

        {{-- Tabla --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full table-auto text-left text-sm">
                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3">Turno #</th>
                        <th class="px-4 py-3">Paciente</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Profesional</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Hora</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Consultorio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3">{{ $r['id'] ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $r['paciente'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $r['email'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $r['profesional'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $r['fecha'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $r['hora'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $estado = $r['estado'] ?? '—';
                                    $badge  = match ($estado) {
                                        'pendiente'                    => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'confirmado'                   => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'cancelado', 'cancelado_tarde' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                        default                        => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs {{ $badge }}">{{ $estado }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $r['consultorio'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No hay turnos para esa fecha. Probá cambiar el día o presioná
                                <strong>Previsualizar</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>


        {{-- Nota cola
        <p class="text-xs text-gray-500">
            Nota: en modo real, se encolan jobs en la cola <code>mail</code>. Asegurate de correr:
            <code>php artisan queue:work --queue=mail,default --tries=3</code>
        </p>  --}}
    </div>
</x-filament-panels::page>





