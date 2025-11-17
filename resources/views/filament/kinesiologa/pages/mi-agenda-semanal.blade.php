{{-- resources/views/filament/kinesiologa/pages/mi-agenda-semanal.blade.php --}}
<x-filament-panels::page>
    {{-- Encabezado / ayuda (SIN botón global aquí) --}}
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold">Configurador semanal</h2>

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Kinesióloga/o:
                        <strong>{{ $profesionalNombre }}</strong>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Especialidad:
                        <strong>{{ $profesionalEspecialidad ?? '—' }}</strong>
                    </p>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Configurá mañana/tarde por día. Podés usar <em>Copiar a todos</em> para replicar un día.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de horarios (una fila por día) --}}
    <div class="mt-4 mb-10 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed text-sm">
                <thead class="sticky top-0 z-10 bg-primary-600 text-white dark:bg-primary-700">
                    <tr class="text-left">
                        <th class="w-[120px] px-4 py-3 font-medium">Día</th>
                        <th class="w-[220px] px-4 py-3 font-medium">Consultorio</th>

                        <th class="w-[52px] px-3 py-3 font-medium">Mañana</th>
                        <th class="w-[120px] px-3 py-3 font-medium">Desde</th>
                        <th class="w-[120px] px-3 py-3 font-medium">Hasta</th>

                        <th class="w-[52px] px-3 py-3 font-medium">Tarde</th>
                        <th class="w-[120px] px-3 py-3 font-medium">Desde</th>
                        <th class="w-[120px] px-3 py-3 font-medium">Hasta</th>

                        <th class="w-[140px] px-4 py-3 font-medium">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($dias as $dia => $nombre)
                    <tr>
                        {{-- Día --}}
                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $nombre }}</td>

                        {{-- Consultorio --}}
                        <td class="px-4 py-3">
                            <select
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                wire:model="estado.{{ $dia }}.consultorio_id"
                            >
                                <option value="">— Sin asignar —</option>
                                @foreach($consultorios as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>

                        {{-- Mañana: Activo + Desde + Hasta --}}
                        <td class="px-3 py-3">
                            <label class="inline-flex items-center gap-1">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                       wire:model="estado.{{ $dia }}.maniana.enabled">
                                <span class="sr-only">Activo</span>
                            </label>
                        </td>
                        <td class="px-3 py-3">
                            <input type="time"
                                   class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                   wire:model.lazy="estado.{{ $dia }}.maniana.desde"
                                   @disabled(!($estado[$dia]['maniana']['enabled'] ?? false))>
                        </td>
                        <td class="px-3 py-3">
                            <input type="time"
                                   class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                   wire:model.lazy="estado.{{ $dia }}.maniana.hasta"
                                   @disabled(!($estado[$dia]['maniana']['enabled'] ?? false))>
                        </td>

                        {{-- Tarde: Activo + Desde + Hasta --}}
                        <td class="px-3 py-3">
                            <label class="inline-flex items-center gap-1">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                       wire:model="estado.{{ $dia }}.tarde.enabled">
                                <span class="sr-only">Activo</span>
                            </label>
                        </td>
                        <td class="px-3 py-3">
                            <input type="time"
                                   class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                   wire:model.lazy="estado.{{ $dia }}.tarde.desde"
                                   @disabled(!($estado[$dia]['tarde']['enabled'] ?? false))>
                        </td>
                        <td class="px-3 py-3">
                            <input type="time"
                                   class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                   wire:model.lazy="estado.{{ $dia }}.tarde.hasta"
                                   @disabled(!($estado[$dia]['tarde']['enabled'] ?? false))>
                        </td>

                        {{-- Acciones (solo Copiar a todos) --}}
                        <td class="px-4 py-3">
                            <x-filament::button
                                color="gray"
                                icon="heroicon-o-arrow-down-tray"
                                wire:click="copiarATodos({{ $dia }})"
                                tag="button"
                                size="sm"
                            >
                                Copiar a todos
                            </x-filament::button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Botón GLOBAL de guardado, reubicado aquí (antes de Excepciones) --}}
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800">
            <x-filament::button
                wire:click="guardarSemana"
                wire:loading.attr="disabled"
                icon="heroicon-o-check"
                tag="button"
            >
                <span wire:loading.remove wire:target="guardarSemana">Guardar toda la semana</span>
                <span wire:loading wire:target="guardarSemana">Guardando…</span>
            </x-filament::button>
        </div>
    </div>

    {{-- ================= Excepciones de disponibilidad ================= --}}
    <div class="mt-8 space-y-4">
        <h3 class="text-lg font-semibold">Excepciones de disponibilidad</h3>

        {{-- Form de nueva excepción --}}
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div class="grid gap-3 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Fecha</label>
                    <input type="date"
                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                           wire:model="nuevaExcepcion.fecha">
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                               wire:model="nuevaExcepcion.bloqueado">
                        <span>Día completo</span>
                    </label>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Desde</label>
                    <input type="time"
                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                           wire:model="nuevaExcepcion.hora_desde"
                           x-bind:disabled="$wire.get('nuevaExcepcion.bloqueado')">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                    <input type="time"
                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                           wire:model="nuevaExcepcion.hora_hasta"
                           x-bind:disabled="$wire.get('nuevaExcepcion.bloqueado')">
                </div>

                <div class="flex items-end justify-end">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox"
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                               wire:model="mantenerFechaExcepcion">
                        <span>Mantener fecha al agregar</span>
                    </label>
                </div>
            </div>

            <div class="mt-3">
                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Motivo (opcional)</label>
                <input type="text"
                       class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                       placeholder="Ej.: Congreso, turno médico, asuntos personales…"
                       wire:model="nuevaExcepcion.motivo">
            </div>

            <div class="mt-3 flex justify-end">
                <x-filament::button icon="heroicon-o-plus-circle" wire:click="agregarExcepcion" tag="button">
                    Agregar excepción
                </x-filament::button>
            </div>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Si marcás <b>Día completo</b>, se bloqueará toda la fecha y se ignorarán las horas.
            </p>
        </div>

        {{-- Listado + filtros --}}
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div class="grid items-end gap-3 md:grid-cols-5">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Desde</label>
                    <input type="date"
                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                           wire:model.defer="filtroExcepcionesDesde">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                    <input type="date"
                           class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                           wire:model.defer="filtroExcepcionesHasta">
                </div>
                <div class="flex gap-2 md:col-span-1">
                    <x-filament::button wire:click="aplicarFiltroExcepciones" tag="button">Aplicar</x-filament::button>
                    <x-filament::button color="gray" wire:click="limpiarFiltroExcepciones" tag="button">Limpiar</x-filament::button>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
                <h4 class="font-medium">Excepciones cargadas</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Fecha</th>
                            <th class="px-4 py-2 text-left font-medium">Tipo</th>
                            <th class="px-4 py-2 text-left font-medium">Desde</th>
                            <th class="px-4 py-2 text-left font-medium">Hasta</th>
                            <th class="px-4 py-2 text-left font-medium">Motivo</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($excepciones as $ex)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['fecha'] }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['bloqueado'] ? 'Día completo' : 'Parcial' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['hora_desde'] ?? '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['hora_hasta'] ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $ex['motivo'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-filament::button color="danger" size="sm" icon="heroicon-o-trash"
                                                        wire:click="eliminarExcepcion({{ $ex['id'] }})" tag="button">
                                        Borrar
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    No hay excepciones cargadas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>



