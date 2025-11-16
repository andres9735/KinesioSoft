<x-filament-panels::page>
    {{-- Encabezado --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Agenda por día</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Profesional: <strong>{{ $this->profesionalNombre }}</strong>
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Pacientes a atender el
                <strong>{{ \Illuminate\Support\Carbon::parse($this->fecha)->format('d/m/Y') }}</strong>.
                Incluye turnos <em>confirmados</em> y <em>pendientes</em>.
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
    <div class="mt-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Fecha</label>
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

    {{-- Resumen --}}
    <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
        Total de turnos: <strong>{{ $this->total }}</strong>
    </div>

    {{-- Tabla --}}
    <div class="mt-4 overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <table class="w-full table-fixed text-sm">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Paciente</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-40">Hora</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-40">Consultorio</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-40">Estado</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-48">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->rows as $r)
                    <tr class="bg-white dark:bg-transparent">
                        <td class="px-4 py-3">{{ $r['paciente'] }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $r['hora'] }}</td>
                        <td class="px-4 py-3">{{ $r['consultorio'] }}</td>
                        <td class="px-4 py-3">
                            <x-filament::badge :color="$r['estadoColor']">
                                {{ $r['estado'] }}
                            </x-filament::badge>

                            @if(($r['reminder_status'] ?? null) === 'confirmed')
                                <span class="ml-2 text-xs text-green-600 dark:text-green-400">✔ confirmado vía email</span>
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
                            @endif>
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
</x-filament-panels::page>




