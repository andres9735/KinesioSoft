<x-filament-panels::page>
    <div class="space-y-5">

        {{-- Encabezado --}}
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Mis turnos</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Acá podés ver, confirmar o cancelar tus turnos.
                </p>
            </div>
        </div>

        {{-- Leyenda de estados --}}
        <div class="text-xs flex flex-wrap gap-3 items-center">
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                <x-heroicon-o-clock class="h-3.5 w-3.5" /> Pendiente
            </span>
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                <x-heroicon-o-check class="h-3.5 w-3.5" /> Confirmado
            </span>
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                <x-heroicon-o-x-mark class="h-3.5 w-3.5" /> Cancelado
            </span>
        </div>

        {{-- Tabla de Filament (definida en la Page via InteractsWithTable) --}}
        {{ $this->table }}

        {{-- Nota opcional --}}
        <p class="text-xs text-gray-500">
            Tip: podés filtrar por estado o por rango de fechas desde los filtros de la tabla.
        </p>
    </div>
</x-filament-panels::page>


