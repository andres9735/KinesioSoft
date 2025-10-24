<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filtros --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-xs font-medium mb-1">Profesional</label>
                <select
                    class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                    wire:model.live="profesionalId"
                >
                    @foreach($profesionales as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Fecha</label>
                <input type="date"
                       class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                       wire:model.live="fecha">
            </div>
        </div>

        {{-- Resultado --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
                <h3 class="font-semibold">Turnos disponibles</h3>
                <span class="text-sm opacity-70">
                    {{ count($slots) }} resultado(s)
                </span>
            </div>

            <div class="p-4">
                @php
                    $fechaSel = \Illuminate\Support\Carbon::parse($fecha ?? now());
                    $esPasado = $fechaSel->isPast() && ! $fechaSel->isToday();
                    $esHoy    = $fechaSel->isToday();
                    // lead time fijo a 30 min (igual que en la Page/servicio)
                    $leadEdge = now()->copy()->addMinutes(30)->format('H:i');
                @endphp

                @if(empty($slots))
                    <p class="text-sm text-gray-500">No hay horarios disponibles para la búsqueda.</p>
                @else
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach($slots as $i => $s)
                            @php
                                $disabled = $esPasado || ($esHoy && ($s['desde'] < $leadEdge));
                            @endphp
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 flex items-center justify-between">
                                <div>
                                    <div class="font-medium">{{ $s['desde'] }} – {{ $s['hasta'] }}</div>
                                    @if(!empty($s['consultorio_id']))
                                        <div class="text-xs opacity-70">Consultorio #{{ $s['consultorio_id'] }}</div>
                                    @endif
                                </div>
                                <x-filament::button
                                    size="sm"
                                    wire:click="reservar({{ $i }})"
                                    wire:target="reservar"
                                    wire:loading.attr="disabled"
                                    :disabled="$disabled"
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
</x-filament-panels::page>

