{{-- resources/views/filament/kinesiologa/pages/agenda-de-hoy.blade.php --}}

<x-filament-panels::page>
    {{-- Encabezado --}}
    <div class="agenda-print-hide mb-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm p-6 flex flex-col gap-6">

        {{-- Fila superior --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold">
                    {{ \Illuminate\Support\Str::of($this->profesionalNombre)->substr(0, 2)->upper() }}
                </div>
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ $this->profesionalNombre }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Agenda — {{ \Illuminate\Support\Carbon::parse($this->fecha)->translatedFormat('l d \\d\\e F Y') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="setHoy"
                    class="rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-2 text-sm font-medium hover:bg-gray-200 dark:hover:bg-white/20"
                >
                    Hoy
                </button>

                <button
                    type="button"
                    onclick="window.print()"
                    class="rounded-lg px-3 py-2 text-sm font-medium ring-1 ring-gray-300/60 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5"
                >
                    Imprimir
                </button>
            </div>
        </div>

        {{-- Fila inferior: fecha + filtros --}}
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Fecha</label>
                <input
                    id="fecha"
                    type="date"
                    wire:model.live="fecha"
                    class="fi-input mt-1 w-44 rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Vista</label>
                <select
                    wire:model.live="vista"
                    class="fi-input mt-1 rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm"
                >
                    <option value="programados">Pendientes y confirmados</option>
                    <option value="atendidos">Atendidos</option>
                    <option value="no_asistio">No asistió</option>
                    <option value="todos">Todos</option>
                </select>
            </div>

            <label class="inline-flex items-center gap-2 text-sm mb-1">
                <input
                    type="checkbox"
                    wire:model.live="soloPendientes"
                    class="rounded border-gray-300"
                    @disabled($this->vista !== 'programados')
                >
                Solo pendientes
                @if($this->vista !== 'programados')
                    <span class="text-xs text-gray-400">(solo en “Programados”)</span>
                @endif
            </label>
        </div>
    </div>


    {{-- Resumen clínico del día --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">

        {{-- Turnos del día (sin cancelados) --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="text-sm text-gray-500">
                Turnos del día (sin cancelados)
            </div>
            <div class="text-2xl font-bold">
                {{
                    collect($this->rows)
                        ->whereNotIn('estado', ['cancelado', 'cancelado_tarde'])
                        ->count()
                }}
            </div>
        </div>

        {{-- Confirmados --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="text-sm text-gray-500">Confirmados</div>
            <div class="text-2xl font-bold text-green-600">
                {{ collect($this->rows)->where('estado', 'confirmado')->count() }}
            </div>
        </div>

        {{-- Pendientes --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="text-sm text-gray-500">Pendientes</div>
            <div class="text-2xl font-bold text-yellow-600">
                {{ collect($this->rows)->where('estado', 'pendiente')->count() }}
            </div>
        </div>

        {{-- Atendidos --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="text-sm text-gray-500">Atendidos</div>
            <div class="text-2xl font-bold text-blue-600">
                {{ collect($this->rows)->where('estado', 'atendido')->count() }}
            </div>
        </div>

        {{-- No asistió --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="text-sm text-gray-500">No asistió</div>
            <div class="text-2xl font-bold text-rose-600">
                {{ collect($this->rows)->where('estado', 'no_asistio')->count() }}
            </div>
        </div>
    </div>


    {{-- Tarjetas clínicas (nuevo diseño) --}}
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-4 agenda-print-hide">
        @forelse ($this->rows as $r)
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-lg font-semibold leading-tight truncate">
                            {{ $r['paciente'] }}
                        </div>

                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            <span class="tabular-nums font-medium">{{ $r['hora'] }}</span>
                            <span class="mx-2 text-gray-300 dark:text-white/20">•</span>
                            <span class="truncate">{{ $r['consultorio'] }}</span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="$r['estadoColor']">
                                {{ $r['estado'] }}
                                @if($r['es_adelanto_automatico'] ?? false)
                                    <span class="ml-1 text-[11px]" title="Turno adelantado automáticamente">⚡</span>
                                @endif
                            </x-filament::badge>

                            @if(($r['reminder_status'] ?? null) === 'confirmed')
                                <span class="text-xs text-green-600 dark:text-green-400">
                                    ✔ confirmado vía email
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 shrink-0">
                        {{-- Si ya hay consulta, mostramos "Ver consulta". Si no, "Iniciar consulta" --}}
                        @if(($r['tiene_consulta'] ?? false) && !empty($r['consulta_id']))
                            <a
                                href="{{ \App\Filament\Kinesiologa\Pages\EvaluacionInicial::getUrl(['turno' => $r['id']]) }}"
                                class="inline-flex justify-center rounded-lg bg-indigo-600 text-white px-3 py-2 text-sm font-semibold hover:bg-indigo-700"
                                title="Abrir evaluación guardada"
                            >
                                👁 Ver consulta
                            </a>
                        @else
                            <button
                                type="button"
                                wire:click="iniciarConsulta({{ $r['id'] }})"
                                class="rounded-lg bg-emerald-600 text-white px-3 py-2 text-sm font-semibold hover:bg-emerald-700"
                                title="Iniciar evaluación inicial"
                            >
                                ▶ Iniciar consulta
                            </button>
                        @endif

                        {{-- ✅ Marcar No asistió (solo en Programados + turno finalizado + sin consulta) --}}
                        @if(
                            ($this->vista ?? 'programados') === 'programados'
                            && !($r['tiene_consulta'] ?? false)
                            && ($r['puede_marcar_no_asistio'] ?? false)
                        )
                            <button
                                type="button"
                                wire:click="marcarNoAsistio({{ $r['id'] }})"
                                class="rounded-lg bg-rose-600 text-white px-3 py-2 text-sm font-semibold hover:bg-rose-700"
                                title="Marcar como No asistió (solo cuando ya pasó la hora de fin)"
                            >
                                🚫 No asistió
                            </button>
                        @endif

                        {{-- Historia clínica --}}
                        @if (\Illuminate\Support\Facades\Route::has('hc.paciente'))
                            <a
                                href="{{ route('hc.paciente', ['paciente' => $r['paciente_id']]) }}"
                                class="inline-flex justify-center rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-2 text-sm font-medium hover:bg-gray-200 dark:hover:bg-white/20"
                            >
                                Historia
                            </a>
                        @else
                            <button
                                type="button"
                                disabled
                                class="rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-2 text-sm font-medium opacity-50 cursor-not-allowed"
                            >
                                Historia
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6 text-center text-gray-500 dark:text-gray-400">
                No hay turnos para la fecha seleccionada.
            </div>
        @endforelse
    </div>


    {{-- Calendario (vista día) --}}
    <div class="mt-4 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
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

    {{-- Estilos específicos para impresión + FullCalendar (pantalla) --}}
    @push('styles')
        <style>
            /* =========================
            * FullCalendar (pantalla)
            * ========================= */
            .fc .kine-event {
                border-radius: 10px;
                font-weight: 600;
            }

            /* ✅ NO ASISTIÓ (gris/rojo tenue) */
            .fc .estado-no-asistio {
                background: #f3f4f6 !important;       /* gray-100 */
                border: 1px solid #fca5a5 !important; /* red-300 */
                color: #111827 !important;            /* gray-900 */
            }

            .fc .estado-no-asistio .fc-event-main {
                color: #111827 !important;
            }

            /* Borde izquierdo tenue para vista timeGrid */
            .fc .estado-no-asistio.fc-timegrid-event {
                border-left: 6px solid #ef4444 !important; /* red-500 */
            }

            /* =========================
            * Impresión
            * ========================= */
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
                        const vistaSelect = document.querySelector('select[wire\\:model\\.live="vista"]');

                        const fecha = info.startStr.slice(0, 10); // día visible
                        const soloPendientes = soloPendientesInput?.checked ? 1 : 0;
                        const vista = vistaSelect?.value ?? 'programados';

                        const url = @js(route('kinesiologa.agenda.events'))
                            + '?date=' + encodeURIComponent(fecha)
                            + '&solo_pendientes=' + soloPendientes
                            + '&vista=' + encodeURIComponent(vista);


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
                const vistaSelect = document.querySelector('select[wire\\:model\\.live="vista"]');

                // Cuando cambia el checkbox, recargamos eventos
                if (soloPendientesInput) {
                    soloPendientesInput.addEventListener('change', () => {
                        calendar.refetchEvents();
                    });
                }

                // Cuando cambia la vista, recargamos eventos
                if (vistaSelect) {
                    vistaSelect.addEventListener('change', () => {
                        calendar.refetchEvents();
                    });
                }

                // Cuando Livewire actualiza la agenda (datepicker o "Hoy" o cambios de filtros)
                Livewire.on('agenda-updated', ({ fecha, soloPendientes, vista }) => {
                    if (fecha) {
                        calendar.gotoDate(fecha);
                    }

                    // sincronizar UI (por si se actualiza desde setHoy o desde backend)
                    if (typeof soloPendientes !== 'undefined' && soloPendientesInput) {
                        soloPendientesInput.checked = !!soloPendientes;
                    }

                    if (typeof vista !== 'undefined' && vistaSelect) {
                        vistaSelect.value = vista;
                    }

                    calendar.refetchEvents();
                });
            }

            document.addEventListener('DOMContentLoaded', initKineAgenda);
            document.addEventListener('livewire:navigated', initKineAgenda);
        </script>
    @endpush
</x-filament-panels::page>




