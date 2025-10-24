{{-- resources/views/filament/kinesiologa/pages/mi-agenda-semanal.blade.php --}}
<x-filament-panels::page>
    {{-- Encabezado / Ayuda --}}
    <div class="space-y-4">
        <div class="p-4 rounded-xl bg-primary-50 text-primary-900 dark:bg-gray-800/60 dark:text-gray-200">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Configurador semanal</h2>
                    <p class="text-sm opacity-80">
                        Definí tus tramos de <strong>mañana</strong> y <strong>tarde</strong> por día.
                        Usá <em>Guardar</em> para persistir cada día, o <em>Copiar este día a todos</em> para replicar rápidamente.
                    </p>
                </div>
            </div>
        </div>

        {{-- Duración global + info profesional --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold mb-2">Duración global</h3>
                <div class="flex items-center gap-3">
                    <label class="text-sm opacity-80">Duración (min):</label>
                    <input
                        type="number"
                        min="5"
                        step="5"
                        class="w-28 rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                        wire:model.number="duracion"
                    >
                </div>
                <p class="text-xs mt-2 opacity-70">
                    Esta duración se aplicará a todos los bloques que guardes desde esta pantalla.
                </p>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold mb-2">Profesional</h3>
                <p class="text-sm opacity-80">
                    Agenda del usuario
                    (id: <code class="px-1 rounded bg-gray-100 dark:bg-gray-800">{{ $profesionalId }}</code>).
                    @if(request()->has('user_id'))
                        <span class="ml-1">Filtrado con <code>?user_id={{ request('user_id') }}</code>.</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Días de la semana --}}
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($dias as $dia => $nombre)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
                    <h3 class="font-semibold">{{ $nombre }}</h3>

                    <div class="flex items-center gap-2">
                        <x-filament::button color="gray"
                            wire:click="copiarATodos({{ $dia }})"
                            icon="heroicon-o-arrow-down-tray"
                            tag="button"
                            size="sm"
                            class="!py-1">
                            Copiar este día a todos
                        </x-filament::button>

                        <x-filament::button
                            wire:click="guardarDia({{ $dia }})"
                            icon="heroicon-o-check"
                            tag="button"
                            size="sm"
                            class="!py-1">
                            Guardar
                        </x-filament::button>
                    </div>
                </div>

                <div class="p-4 space-y-6">
                    {{-- Consultorio del día --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Consultorio</label>
                        <select
                            class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                            wire:model="estado.{{ $dia }}.consultorio_id"
                        >
                            <option value="">— Sin asignar —</option>
                            @foreach($consultorios as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1 opacity-70">Se aplicará al/los bloques de este día.</p>
                    </div>

                    {{-- Tramo de Mañana --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Mañana</span>
                            <label class="inline-flex items-center gap-2">
                                <span class="text-sm opacity-80">Activo</span>
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    wire:model="estado.{{ $dia }}.maniana.enabled"
                                >
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                                <input
                                    type="time"
                                    class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                                    wire:model.lazy="estado.{{ $dia }}.maniana.desde"
                                    @disabled(!($estado[$dia]['maniana']['enabled'] ?? false))
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                                <input
                                    type="time"
                                    class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                                    wire:model.lazy="estado.{{ $dia }}.maniana.hasta"
                                    @disabled(!($estado[$dia]['maniana']['enabled'] ?? false))
                                >
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700">

                    {{-- Tramo de Tarde --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Tarde</span>
                            <label class="inline-flex items-center gap-2">
                                <span class="text-sm opacity-80">Activo</span>
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    wire:model="estado.{{ $dia }}.tarde.enabled"
                                >
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                                <input
                                    type="time"
                                    class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                                    wire:model.lazy="estado.{{ $dia }}.tarde.desde"
                                    @disabled(!($estado[$dia]['tarde']['enabled'] ?? false))
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                                <input
                                    type="time"
                                    class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                                    wire:model.lazy="estado.{{ $dia }}.tarde.hasta"
                                    @disabled(!($estado[$dia]['tarde']['enabled'] ?? false))
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-end gap-2">
                    <x-filament::button color="gray"
                        wire:click="copiarATodos({{ $dia }})"
                        icon="heroicon-o-arrow-down-tray"
                        tag="button"
                        size="sm"
                        class="!py-1">
                        Copiar este día a todos
                    </x-filament::button>

                    <x-filament::button
                        wire:click="guardarDia({{ $dia }})"
                        icon="heroicon-o-check"
                        tag="button"
                        size="sm"
                        class="!py-1">
                        Guardar
                    </x-filament::button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ================= Excepciones de disponibilidad ================= --}}
    <div class="mt-10 space-y-4">
        <h3 class="text-lg font-semibold">Excepciones de disponibilidad</h3>

        {{-- Form de nueva excepción --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <div class="grid gap-3 md:grid-cols-5">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                    <input
                        type="date"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                        wire:model="nuevaExcepcion.fecha"
                    >
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2">
                        <input
                            type="checkbox"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            wire:model="nuevaExcepcion.bloqueado"
                        >
                        <span class="text-sm">Día completo</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                    <input
                        type="time"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                        wire:model="nuevaExcepcion.hora_desde"
                        x-bind:disabled="$wire.get('nuevaExcepcion.bloqueado')"
                    >
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                    <input
                        type="time"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                        wire:model="nuevaExcepcion.hora_hasta"
                        x-bind:disabled="$wire.get('nuevaExcepcion.bloqueado')"
                    >
                </div>

                {{-- Nuevo: mantener fecha al agregar --}}
                <div class="flex items-end justify-end">
                    <label class="inline-flex items-center gap-2">
                        <input
                            type="checkbox"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            wire:model="mantenerFechaExcepcion"
                        >
                        <span class="text-sm">Mantener fecha al agregar</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo (opcional)</label>
                <input
                    type="text"
                    class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Ej.: Congreso, turno médico, asuntos personales…"
                    wire:model="nuevaExcepcion.motivo"
                >
            </div>

            <div class="flex justify-end">
                <x-filament::button icon="heroicon-o-plus-circle" wire:click="agregarExcepcion" tag="button">
                    Agregar excepción
                </x-filament::button>
            </div>

            <p class="text-xs opacity-70">
                Si marcás <b>Día completo</b>, se bloqueará toda la fecha y se ignorarán las horas.
                Para bloqueo parcial, desmarcá y completá <em>Desde/Hasta</em>.
            </p>
        </div>

        {{-- Filtros de fecha --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="grid gap-3 md:grid-cols-5 items-end">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                    <input
                        type="date"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                        wire:model.defer="filtroExcepcionesDesde"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                    <input
                        type="date"
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700 focus:border-primary-500 focus:ring-primary-500"
                        wire:model.defer="filtroExcepcionesHasta"
                    >
                </div>

                <div class="flex gap-2 md:col-span-1">
                    <x-filament::button wire:click="aplicarFiltroExcepciones" tag="button">
                        Aplicar
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="limpiarFiltroExcepciones" tag="button">
                        Limpiar
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Listado de excepciones --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60">
                <h4 class="font-medium">Excepciones cargadas</h4>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
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
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($excepciones as $ex)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['fecha'] }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if($ex['bloqueado']) Día completo @else Parcial @endif
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['hora_desde'] ?? '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $ex['hora_hasta'] ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $ex['motivo'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-filament::button color="danger"
                                        size="sm"
                                        icon="heroicon-o-trash"
                                        wire:click="eliminarExcepcion({{ $ex['id'] }})"
                                        tag="button">
                                        Borrar
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colspan="6">
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

