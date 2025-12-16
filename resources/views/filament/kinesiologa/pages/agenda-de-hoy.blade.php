{{-- resources/views/filament/kinesiologa/pages/agenda-de-hoy.blade.php --}}

<x-filament-panels::page>
    {{-- Encabezado --}}
    <div class="flex items-start justify-between agenda-print-hide">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Agenda por día</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Profesional: <strong>{{ $this->profesionalNombre }}</strong>
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Pacientes a atender el
                <strong>{{ \Illuminate\Support\Carbon::parse($this->fecha)->format('d/m/Y') }}</strong>.
                Incluye turnos <em>confirmados</em> y <em>pendientes</em>.
                Los turnos adelantados se indican con el icono
                <span title="Turno adelantado automáticamente">⚡</span>.
            </p>
        </div>

        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ring-1 ring-gray-300/60 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5"
        >
            Imprimir
        </button>
    </div>

    {{-- Controles: fecha + Hoy + Solo pendientes --}}
    <div class="mt-6 flex flex-wrap items-end gap-4 agenda-print-hide">
        <div>
            <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Fecha
            </label>
            <input
                id="fecha"
                type="date"
                wire:model.live="fecha"
                class="fi-input mt-1 w-44 rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm"
            />
        </div>

        <button
            type="button"
            wire:click="setHoy"
            class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-2 text-sm font-medium hover:bg-gray-200 dark:hover:bg-white/20"
        >
            Hoy
        </button>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="soloPendientes" class="rounded border-gray-300">
            Solo pendientes
        </label>
    </div>

    {{-- Calendario (vista día) --}}
    <div class="mt-8 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-700 dark:border-white/10 dark:text-gray-100">
            Agenda visual del día
        </div>

        {{-- Livewire no toca este div, FullCalendar manda --}}
        <div
            id="kine-day-calendar"
            wire:ignore
            class="p-4"
            style="min-height: 600px;"
        ></div>
    </div>

    {{-- Resumen --}}
    <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
        Total de turnos: <strong>{{ $this->total }}</strong>
    </div>

    {{-- Tabla tradicional (detalle) --}}
    <div class="mt-4 overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <table class="agenda-table w-full table-fixed text-sm">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                        Paciente
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-40">
                        Hora
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-40">
                        Consultorio
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-40">
                        Estado
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-48">
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->rows as $r)
                    <tr class="bg-white dark:bg-transparent">
                        <td class="px-4 py-3 agenda-col-paciente">
                            {{ $r['paciente'] }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ $r['hora'] }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $r['consultorio'] }}
                        </td>
                        <td class="px-4 py-3">
                            <x-filament::badge :color="$r['estadoColor']">
                                {{ $r['estado'] }}

                                @if($r['es_adelanto_automatico'] ?? false)
                                    <span
                                        class="ml-1 text-[11px]"
                                        title="Turno adelantado automáticamente desde un hueco libre"
                                    >
                                        ⚡
                                    </span>
                                @endif
                            </x-filament::badge>

                            @if(($r['reminder_status'] ?? null) === 'confirmed')
                                <span class="ml-2 text-xs text-green-600 dark:text-green-400">
                                    ✔ confirmado vía email
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (\Illuminate\Support\Facades\Route::has('hc.paciente'))
                                <a href="{{ route('hc.paciente', ['paciente' => $r['paciente_id']]) }}"
                                   class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-1.5 text-xs font-medium hover:bg-gray-200 dark:hover:bg-white/20">
                                    Ver historia clínica
                                </a>
                            @else
                                <button type="button" disabled title="Próximamente"
                                        class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-1.5 text-xs font-medium opacity-50 cursor-not-allowed">
                                    Ver historia clínica
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            No hay turnos para la fecha seleccionada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Estilos específicos para impresión --}}
    @push('styles')
        <style>
            @media print {
                html, body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                    background: #ffffff !important;
                    color: #000000 !important;
                }

                /* Ocultar controles / botones que sobran en papel */
                .agenda-print-hide {
                    display: none !important;
                }

                /* Hacer más visibles los eventos en el papel */
                .fc .fc-timegrid-event {
                    background-color: #dbeafe !important; /* blue-100 */
                    border: 1px solid #1d4ed8 !important;  /* blue-700 */
                    color: #000000 !important;
                    font-size: 11px !important;
                    font-weight: 500 !important;
                }

                .fc .fc-timegrid-slot,
                .fc .fc-col-header-cell {
                    border-color: #9ca3af !important; /* gray-400 */
                }

                /* Que las líneas horarias no sean tan altas en impresión */
                .fc .fc-timegrid-slot {
                    height: 1.4em !important;
                }

                /* Tabla: que no se rompa el encabezado "Paciente" */
                .agenda-table {
                    table-layout: auto !important;
                }

                .agenda-table th,
                .agenda-table td {
                    font-size: 11px !important;
                    padding: 4px 6px !important;
                    white-space: normal !important;
                }

                .agenda-col-paciente {
                    max-width: 220px;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

        <script>
            function initKineAgenda() {
                const container = document.getElementById('kine-day-calendar');
                if (!container) return;

                // Evitar inicializar dos veces
                if (container.dataset.fcInitialized === '1') {
                    return;
                }
                container.dataset.fcInitialized = '1';

                if (!window.FullCalendar || !window.FullCalendar.Calendar) {
                    console.error('[FullCalendar] Bundle global no encontrado', window.FullCalendar);
                    return;
                }

                const Calendar = window.FullCalendar.Calendar;

                const calendar = new Calendar(container, {
                    initialView: 'timeGridDay',
                    locale: 'es',
                    slotMinTime: '07:00:00',
                    slotMaxTime: '21:00:00',
                    allDaySlot: false,
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    initialDate: @js($this->fecha),

                    // Sincroniza datepicker + Livewire cuando se usa prev/next/today
                    datesSet(info) {
                        const fechaInput = document.getElementById('fecha');
                        if (!fechaInput) return;

                        const nuevaFecha = info.view.currentStart.toISOString().slice(0, 10);

                        if (fechaInput.value !== nuevaFecha) {
                            fechaInput.value = nuevaFecha;
                            // Dispara el evento para que Livewire actualice $fecha
                            fechaInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    },

                    // Siempre usamos el día visible de FullCalendar
                    events(info, successCallback, failureCallback) {
                        const soloPendientesInput = document.querySelector('input[wire\\:model\\.live="soloPendientes"]');

                        const fecha = info.startStr.slice(0, 10); // día visible
                        const soloPendientes = soloPendientesInput?.checked ? 1 : 0;

                        const url = @js(route('kinesiologa.agenda.events'))
                            + '?date=' + encodeURIComponent(fecha)
                            + '&solo_pendientes=' + soloPendientes;

                        console.log('[FullCalendar] Cargando eventos desde:', url);

                        fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('HTTP ' + response.status);
                                }
                                return response.json();
                            })
                            .then(events => {
                                console.log('[FullCalendar] Eventos recibidos:', events);
                                successCallback(events);
                            })
                            .catch(error => {
                                console.error('[FullCalendar] Error cargando eventos:', error);
                                failureCallback(error);
                            });
                    },
                });

                calendar.render();

                const soloPendientesInput = document.querySelector('input[wire\\:model\\.live="soloPendientes"]');

                // Cuando cambia el checkbox, recargamos eventos
                if (soloPendientesInput) {
                    soloPendientesInput.addEventListener('change', () => {
                        calendar.refetchEvents();
                    });
                }

                // Cuando Livewire actualiza la agenda (datepicker o "Hoy")
                Livewire.on('agenda-updated', ({ fecha }) => {
                    if (!fecha) return;
                    calendar.gotoDate(fecha);
                    calendar.refetchEvents();
                });
            }

            document.addEventListener('DOMContentLoaded', initKineAgenda);
            document.addEventListener('livewire:navigated', initKineAgenda);
        </script>
    @endpush
</x-filament-panels::page>




