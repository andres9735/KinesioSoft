<div class="space-y-6">
    {{-- Filtros superiores --}}
    <div class="flex flex-col md:flex-row md:items-end gap-4">
        <div class="w-full md:w-72">
            <label class="block text-sm font-medium mb-1">Profesional</label>
            <select class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                    wire:model.live="profesionalId">
                <option value="">— Seleccioná —</option>
                @foreach($profesionales as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full md:w-64">
            <label class="block text-sm font-medium mb-1">Consultorio (opcional)</label>
            <input type="number" min="1" class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                   placeholder="id_consultorio"
                   wire:model.live="consultorioId">
        </div>

        <div class="flex gap-2">
            <x-filament::button color="gray" wire:click="semanaAnterior" icon="heroicon-o-chevron-left">Semana anterior</x-filament::button>
            <x-filament::button color="gray" wire:click="hoy" icon="heroicon-o-calendar">Hoy</x-filament::button>
            <x-filament::button color="gray" wire:click="semanaSiguiente" icon="heroicon-o-chevron-right">Semana siguiente</x-filament::button>
        </div>
    </div>

    {{-- Semana --}}
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
        @foreach($diasSemana as $d)
            @php
                $total = $resumenPorDia[$d['fecha']] ?? 0;
                $isSelected = $diaSeleccionado === $d['fecha'];
                $bg = $total > 0 ? 'bg-emerald-50 border-emerald-300' : 'bg-rose-50 border-rose-300';
            @endphp
            <button
                class="rounded-xl border p-3 text-left {{ $bg }} {{ $isSelected ? 'ring-2 ring-offset-2 ring-primary-500' : '' }}"
                wire:click="seleccionarDia('{{ $d['fecha'] }}')"
            >
                <div class="font-medium">{{ \Carbon\Carbon::parse($d['fecha'])->isoFormat('dddd D') }}</div>
                <div class="text-xs opacity-70">
                    {{ $total > 0 ? ($total.' turnos disponibles') : 'Sin disponibilidad' }}
                </div>
            </button>
        @endforeach
    </div>

    {{-- Helpers de fecha seleccionada --}}
    @php
        $diaSel    = \Carbon\Carbon::parse($diaSeleccionado);
        $esPasado  = $diaSel->isBefore(\Carbon\Carbon::today());
        $esHoy     = $diaSel->isToday();
        $horaAhora = \Carbon\Carbon::now()->format('H:i');
    @endphp

    {{-- Detalle del día seleccionado --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
            <div class="font-semibold">
                {{ \Carbon\Carbon::parse($diaSeleccionado)->isoFormat('dddd D [de] MMMM YYYY') }}
            </div>
            <div class="text-sm opacity-70">
                {{ count($slotsDelDia) }} turnos disponibles
            </div>
        </div>

        @if ($esPasado)
            <div class="px-4 py-2 text-sm text-rose-700 bg-rose-50 border-t border-rose-200">
                No se pueden reservar turnos en fechas anteriores a la actual.
            </div>
        @endif

        <div class="p-4">
            @if (count($slotsDelDia) === 0)
                <div class="text-sm text-gray-600 dark:text-gray-400">No hay turnos disponibles para este día.</div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-2">
                    @foreach($slotsDelDia as $slot)
                        @php
                            // Deshabilitado si el día es pasado, o si es hoy y la hora de inicio ya pasó
                            $slotDeshabilitado = $esPasado || ($esHoy && $slot['desde'] <= $horaAhora);
                        @endphp
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-2 flex items-center justify-between {{ $slotDeshabilitado ? 'opacity-60' : '' }}">
                            <span class="text-sm font-medium">{{ $slot['desde'] }}–{{ $slot['hasta'] }}</span>

                            <x-filament::button
                                size="xs"
                                icon="heroicon-o-check"
                                wire:click="reservar({{ $loop->index }})"
                                wire:target="reservar"
                                wire:loading.attr="disabled"
                                @if($slotDeshabilitado) disabled @endif
                                class="{{ $slotDeshabilitado ? 'cursor-not-allowed' : '' }}"
                            >
                                <span wire:loading.remove wire:target="reservar">Reservar</span>
                                <span wire:loading wire:target="reservar">Reservando…</span>
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
