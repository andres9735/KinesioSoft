<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Intro / preguntas --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4">
            <div class="text-lg font-semibold">
                Estás por solicitar un turno para atenderte en nuestros consultorios
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Por favor respondé estas preguntas para ofrecerte las opciones más adecuadas:
            </p>

            {{-- Checkbox: profesional específico --}}
            <label class="flex items-center gap-2">
                <input type="checkbox" class="rounded" wire:model.live="eligeProfesional">
                <span class="font-medium">Necesito un turno con un profesional específico</span>
            </label>

            @if($eligeProfesional)
                <div class="pl-6">
                    <div class="text-xs mb-1">Por favor seleccioná el profesional con el que deseás atenderte</div>
                    <select
                        class="w-full md:w-96 rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        wire:model.live="profesionalId"
                    >
                        <option value="">— Seleccioná —</option>
                        @foreach($profesionales as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Checkbox: consultar por día --}}
            <label class="flex items-center gap-2">
                <input type="checkbox" class="rounded" wire:model.live="eligeFecha">
                <span class="font-medium">Quiero consultar los turnos de un día en particular</span>
            </label>

            @if($eligeFecha)
                <div class="pl-6">
                    <div class="text-xs mb-1">Por favor seleccioná la fecha en la que deseás consultar disponibilidad</div>
                    <input
                        type="date"
                        class="w-full md:w-64 rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        wire:model.live="fecha"
                    >
                </div>
            @endif

            {{-- Acción directa: Consultar ahora --}}
            <div class="pt-2">
                <x-filament::button
                    color="primary"
                    wire:click="consultarAhora"
                    wire:target="consultarAhora"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-magnifying-glass"
                >
                    <span wire:loading.remove wire:target="consultarAhora">Consultar ahora</span>
                    <span wire:loading wire:target="consultarAhora">Buscando…</span>
                </x-filament::button>
                <span class="text-xs text-gray-500 ml-2">
                    Sin elegir nada te mostramos las primeras disponibilidades entre todos los profesionales.
                </span>
            </div>
        </div>

        {{-- Sugerencias automáticas (lista) cuando NO se está consultando por día --}}
        @if(!$eligeFecha)
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/20 overflow-hidden">
                <div class="px-4 py-3 bg-amber-100/70 dark:bg-amber-900/40 flex items-center justify-between">
                    <h3 class="font-semibold">Próximos turnos disponibles</h3>
                    <span class="text-sm opacity-70">{{ count($sugeridos) }} resultado(s)</span>
                </div>

                <div class="p-4">
                    @if(empty($sugeridos))
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            No encontramos turnos disponibles en los próximos días con los filtros actuales.
                            Probá seleccionar un día específico.
                        </div>
                    @else
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            @foreach($sugeridos as $i => $s)
                                @php
                                    $avg = isset($s['rating_avg']) ? number_format((float)$s['rating_avg'], 1) : '0.0';
                                    $cnt = (int)($s['rating_count'] ?? 0);
                                @endphp
                                <div class="rounded-lg border border-amber-200 dark:border-amber-700 p-3 flex flex-col justify-between bg-white/70 dark:bg-transparent">
                                    <div class="text-sm space-y-1">
                                        <div class="font-medium">
                                            {{ \Carbon\Carbon::parse($s['fecha'])->isoFormat('dddd D [de] MMMM YYYY') }}
                                        </div>
                                        <div>{{ $s['desde'] }} – {{ $s['hasta'] }}</div>
                                        <div class="text-xs opacity-70">
                                            @if(!empty($s['especialidad']))
                                                Especialista en {{ $s['especialidad'] }} —
                                            @endif
                                            {{ $s['profesional'] ?? 'Profesional' }}
                                            ({{ $avg }}/5.0 @if($cnt>0) • {{ $cnt }} opinión{{ $cnt===1 ? '' : 'es' }} @endif)
                                        </div>
                                        @if(!empty($s['consultorio_id']))
                                            <div class="text-xs opacity-70">Consultorio #{{ $s['consultorio_id'] }}</div>
                                        @endif
                                    </div>
                                    <x-filament::button
                                        size="sm"
                                        class="mt-3"
                                        wire:click="reservarSugerido({{ $i }})"
                                        wire:target="reservarSugerido({{ $i }})"
                                        wire:loading.attr="disabled"
                                    >
                                        <span wire:loading.remove wire:target="reservarSugerido({{ $i }})">Reservar</span>
                                        <span wire:loading wire:target="reservarSugerido({{ $i }})">Reservando…</span>
                                    </x-filament::button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Resultado del día seleccionado --}}
        @if($eligeFecha)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
                    <h3 class="font-semibold">
                        Turnos disponibles
                        <span class="text-sm font-normal opacity-70">
                            — {{ \Carbon\Carbon::parse($fecha)->isoFormat('dddd D [de] MMMM YYYY') }}
                        </span>
                    </h3>
                    <span class="text-sm opacity-70">{{ count($slots) }} resultado(s)</span>
                </div>

                <div class="p-4">
                    @php
                        $fechaSel = \Carbon\Carbon::parse($fecha ?? now());
                        $esPasado = $fechaSel->isPast() && !$fechaSel->isToday();
                        $esHoy    = $fechaSel->isToday();
                        $leadEdge = now()->addMinutes(30)->format('H:i'); // mismo valor que en Page
                    @endphp

                    @if($esPasado)
                        <div class="text-sm text-red-600">No podés reservar en fechas pasadas.</div>
                    @elseif(empty($slots))
                        <p class="text-sm text-gray-500">No hay horarios disponibles para la búsqueda.</p>
                    @else
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            @foreach($slots as $i => $s)
                                @php
                                    $disabled = $esPasado || ($esHoy && ($s['desde'] < $leadEdge));
                                    $avg = isset($s['rating_avg']) ? number_format((float)$s['rating_avg'], 1) : '0.0';
                                    $cnt = (int)($s['rating_count'] ?? 0);
                                @endphp
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 flex flex-col justify-between">
                                    <div class="text-sm space-y-1">
                                        <div class="font-medium">{{ $s['desde'] }} – {{ $s['hasta'] }}</div>
                                        <div class="text-xs opacity-70">
                                            @if(!empty($s['especialidad']))
                                                Especialista en {{ $s['especialidad'] }} —
                                            @endif
                                            {{ $s['profesional'] ?? 'Profesional' }}
                                            ({{ $avg }}/5.0 @if($cnt>0) • {{ $cnt }} opinión{{ $cnt===1 ? '' : 'es' }} @endif)
                                        </div>
                                        @if(!empty($s['consultorio_id']))
                                            <div class="text-xs opacity-70">Consultorio #{{ $s['consultorio_id'] }}</div>
                                        @endif
                                    </div>
                                    <x-filament::button
                                        size="sm"
                                        class="mt-3"
                                        :disabled="$disabled"
                                        wire:click="reservar({{ $i }})"
                                        wire:target="reservar({{ $i }})"
                                        wire:loading.attr="disabled"
                                    >
                                        <span wire:loading.remove wire:target="reservar({{ $i }})">Reservar</span>
                                        <span wire:loading wire:target="reservar({{ $i }})">Reservando…</span>
                                    </x-filament::button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>



