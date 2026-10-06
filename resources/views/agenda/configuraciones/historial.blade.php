<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Historial de configuraciones</h1>
            <p class="text-sm text-gray-500">Configuraciones finalizadas y configuraciones futuras anuladas.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="listado-historial">
                <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 id="listado-historial" class="text-lg font-semibold text-gray-900">Configuraciones conservadas</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $configuraciones->total() }} resultado(s), con los más recientes primero.</p>
                    </div>
                    <a href="{{ route('turnos.agenda.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Volver a la agenda</a>
                </div>

                @if ($configuraciones->isEmpty())
                    <div class="px-5 py-12 text-center sm:px-6">
                        <h3 class="font-semibold text-gray-900">No existen configuraciones históricas ni futuras anuladas para consultar.</h3>
                        <a href="{{ route('turnos.agenda.index') }}" class="mt-5 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">Volver a la agenda</a>
                    </div>
                @else
                    <div class="hidden md:block">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Historial paginado de configuraciones de agenda</caption>
                            <thead class="border-b border-gray-100 bg-gray-50 text-gray-600">
                                <tr>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Período</th>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Duración</th>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Resumen</th>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Estado</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold sm:px-6">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($configuraciones as $configuracion)
                                    @php($estadoConfiguracion = $configuracion->estadoEn($hoy))
                                    <tr>
                                        <td class="px-5 py-4 text-gray-700 sm:px-6"><span class="block">Desde {{ $configuracion->vigencia_desde->format('d/m/Y') }}</span><span class="block">Hasta {{ $configuracion->vigencia_hasta?->format('d/m/Y') ?? 'Sin fecha de fin' }}</span></td>
                                        <td class="px-5 py-4 text-gray-700 sm:px-6">{{ $configuracion->duracion_turno_minutos }} minutos</td>
                                        <td class="px-5 py-4 text-gray-700 sm:px-6">{{ $configuracion->dias_atencion_count }} día(s), {{ $configuracion->franjas_horarias_count }} franja(s)</td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <x-agenda.estado-configuracion-badge :estado="$estadoConfiguracion" />
                                            @if ($configuracion->anulado_at !== null)
                                                <span class="mt-2 block text-xs text-gray-500">Anulada el {{ $configuracion->anulado_at->format('d/m/Y H:i') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-right sm:px-6"><a href="{{ route('turnos.agenda.configuraciones.show', ['configuracion' => $configuracion, 'origen' => 'historial', 'page' => $configuraciones->currentPage()]) }}" class="inline-flex rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200">Consultar</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-gray-100 md:hidden">
                        @foreach ($configuraciones as $configuracion)
                            @php($estadoConfiguracion = $configuracion->estadoEn($hoy))
                            <article class="space-y-3 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-semibold text-gray-900">{{ $configuracion->vigencia_desde->format('d/m/Y') }} a {{ $configuracion->vigencia_hasta?->format('d/m/Y') ?? 'Sin fecha de fin' }}</h3>
                                    <x-agenda.estado-configuracion-badge :estado="$estadoConfiguracion" class="shrink-0" />
                                </div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div><dt class="text-gray-500">Duración</dt><dd class="font-semibold text-gray-900">{{ $configuracion->duracion_turno_minutos }} minutos</dd></div>
                                    <div><dt class="text-gray-500">Resumen</dt><dd class="font-semibold text-gray-900">{{ $configuracion->dias_atencion_count }} día(s), {{ $configuracion->franjas_horarias_count }} franja(s)</dd></div>
                                </dl>
                                @if ($configuracion->anulado_at !== null)
                                    <p class="text-xs text-gray-500">Anulada el {{ $configuracion->anulado_at->format('d/m/Y H:i') }}</p>
                                @endif
                                <a href="{{ route('turnos.agenda.configuraciones.show', ['configuracion' => $configuracion, 'origen' => 'historial', 'page' => $configuraciones->currentPage()]) }}" class="inline-flex rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700">Consultar</a>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($configuraciones->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4 sm:px-6">{{ $configuraciones->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
