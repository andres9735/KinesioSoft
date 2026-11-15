<x-app-layout>
    @php
        $estados = ['PENDIENTE' => 'Pendiente', 'CONFIRMADO' => 'Confirmado', 'EN_ATENCION' => 'En atención', 'ATENDIDO' => 'Atendido', 'AUSENTE' => 'Ausente', 'CANCELADO' => 'Cancelado'];
        $tipos = ['PRIMERA_CONSULTA' => 'Primera consulta', 'SEGUIMIENTO' => 'Seguimiento'];
    @endphp
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-gray-900">Agenda de atención</h1>
    </x-slot>

    <div class="uc05-agenda py-5 sm:py-6" x-data="agendaAtencion">
        <section class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8" aria-labelledby="jornada-agenda">
            <div>
                <div class="min-w-0">
                    <h2 id="jornada-agenda" class="text-sm font-medium text-gray-700">
                        @if ($esPeriodo)
                            Período del <time datetime="{{ $fecha->toDateString() }}">{{ $fecha->format('d/m/Y') }}</time>
                            al <time datetime="{{ $hasta->toDateString() }}">{{ $hasta->format('d/m/Y') }}</time>
                        @else
                            Jornada del <time datetime="{{ $fecha->toDateString() }}">{{ $fecha->format('d/m/Y') }}</time>
                        @endif
                    </h2>
                    <p class="mt-1 text-xs text-gray-500">Horarios locales del consultorio. Turnos ordenados por hora de inicio.</p>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="min-w-0 space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="text-sm font-semibold text-gray-900">Consultar una jornada</h3>

                <form method="GET" action="{{ route('turnos.agenda-atencion.index') }}" aria-label="Consultar una jornada" class="uc05-consulta-jornada">
                    @foreach ($filtros as $nombre => $valor)
                        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                    @endforeach
                    <div class="min-w-0 flex-1">
                        <x-input-label for="fecha" value="Fecha de la jornada" />
                        <x-text-input id="fecha" name="fecha" type="date" :value="$fecha->toDateString()" required
                            class="mt-1 min-h-11 w-full" :aria-invalid="$errors->has('fecha') ? 'true' : 'false'"
                            :aria-describedby="$errors->has('fecha') ? 'fecha-ayuda fecha-error' : 'fecha-ayuda'" />
                        <p id="fecha-ayuda" class="mt-1 text-sm text-gray-600">Podés consultar fechas pasadas o futuras.</p>
                        @if ($errors->has('fecha'))
                            <x-input-error id="fecha-error" role="alert" class="mt-2" :messages="$errors->get('fecha')" />
                        @endif
                    </div>
                    <x-primary-button class="uc05-consulta-boton min-h-11 justify-center self-start sm:mt-6">Consultar fecha</x-primary-button>
                </form>
                @if (! $esPeriodo)
                <nav aria-label="Navegación entre jornadas" class="flex flex-wrap gap-x-4">
                    <a href="{{ route('turnos.agenda-atencion.index', ['fecha' => $fecha->subDay()->toDateString()] + $filtros) }}" rel="prev"
                       aria-label="Jornada anterior, {{ $fecha->subDay()->format('d/m/Y') }}"
                       class="uc05-enlace inline-flex min-h-11 items-center rounded-lg px-2 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <span aria-hidden="true" class="mr-1">←</span> Día anterior
                    </a>
                    <a href="{{ route('turnos.agenda-atencion.index', ['fecha' => $fecha->addDay()->toDateString()] + $filtros) }}" rel="next"
                       aria-label="Jornada siguiente, {{ $fecha->addDay()->format('d/m/Y') }}"
                       class="uc05-enlace inline-flex min-h-11 items-center rounded-lg px-2 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Día siguiente <span aria-hidden="true" class="ml-1">→</span>
                    </a>
                </nav>
                @endif
                </div>

                <form method="GET" action="{{ route('turnos.agenda-atencion.index') }}" aria-label="Consultar un período" class="min-w-0 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    @foreach ($filtros as $nombre => $valor)
                        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                    @endforeach
                    <fieldset class="min-w-0 space-y-3">
                        <legend class="text-sm font-semibold text-gray-900">Consultar un período</legend>
                        <div class="grid grid-cols-1 gap-3 min-[360px]:grid-cols-2">
                            @foreach (['desde' => ['Fecha inicial', $fecha], 'hasta' => ['Fecha final', $hasta]] as $nombre => [$etiqueta, $valor])
                                <div class="min-w-0">
                                    <x-input-label :for="$nombre" :value="$etiqueta" />
                                    <x-text-input :id="$nombre" :name="$nombre" type="date" :value="$valor->toDateString()" required
                                        class="mt-1 min-h-11 w-full" :aria-invalid="$errors->has($nombre) ? 'true' : 'false'"
                                        :aria-describedby="$errors->has($nombre) ? $nombre.'-error' : null" />
                                    <x-input-error :id="$nombre.'-error'" role="alert" class="mt-2" :messages="$errors->get($nombre)" />
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-primary-button class="uc05-consulta-boton min-h-11 justify-center">Consultar período</x-primary-button>
                            <x-secondary-button type="button" x-on:click="limpiarFechas($event)" class="uc05-enlace min-h-11 justify-center">Limpiar fechas</x-secondary-button>
                        </div>
                    </fieldset>
                </form>
            </div>

            <details class="rounded-2xl border border-gray-200 bg-white shadow-sm" @if ($errors->any()) open @endif>
                <summary class="cursor-pointer rounded-2xl px-4 py-3 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:px-5">Filtrar turnos @if ($filtros !== [])<span class="ml-2 rounded-full bg-indigo-50 px-2 py-1 text-xs text-indigo-700">{{ count($filtros) }} activos</span>@else<span class="ml-2 font-normal text-gray-500">Paciente, horario, estado y tipo</span>@endif</summary>
            <form method="GET" action="{{ route('turnos.agenda-atencion.index') }}" class="border-t border-gray-100 p-4 sm:p-5">
                @foreach ($contextoTemporal as $nombre => $valor)
                    <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                @endforeach
                <fieldset class="min-w-0 space-y-4" aria-describedby="filtros-ayuda">
                    <legend class="sr-only">Filtrar turnos</legend>
                    <p id="filtros-ayuda" class="text-sm text-gray-600">Buscá por parte del nombre o apellido del paciente. El horario corresponde al inicio del turno, en hora local.</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        @foreach (['paciente' => ['Buscar paciente', 'search'], 'hora_desde' => ['Hora de inicio desde (inclusive)', 'time'], 'hora_hasta' => ['Hora de inicio hasta (inclusive)', 'time']] as $nombre => [$etiqueta, $tipoInput])
                            <div class="min-w-0">
                                <x-input-label :for="$nombre" :value="$etiqueta" />
                                <div @class(['mt-1', 'uc05-campo-hora' => $tipoInput === 'time'])>
                                    <x-text-input :id="$nombre" :name="$nombre" :type="$tipoInput" :value="$filtros[$nombre] ?? ''"
                                        class="min-h-11 w-full" :aria-invalid="$errors->has($nombre) ? 'true' : 'false'"
                                        :aria-describedby="$errors->has($nombre) ? 'filtros-ayuda '.$nombre.'-error' : 'filtros-ayuda'" />
                                    @if ($tipoInput === 'time')
                                        <svg class="uc05-reloj" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                                    @endif
                                </div>
                                <x-input-error :id="$nombre.'-error'" role="alert" class="mt-2" :messages="$errors->get($nombre)" />
                            </div>
                        @endforeach
                        @foreach (['estado' => ['Estado', $estados], 'tipo' => ['Tipo de consulta', $tipos]] as $nombre => [$etiqueta, $opciones])
                            <div class="min-w-0">
                                <x-input-label :for="$nombre" :value="$etiqueta" />
                                <select id="{{ $nombre }}" name="{{ $nombre }}"
                                    aria-invalid="{{ $errors->has($nombre) ? 'true' : 'false' }}"
                                    @if ($errors->has($nombre)) aria-describedby="{{ $nombre }}-error" @endif
                                    class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos</option>
                                    @foreach ($opciones as $valor => $texto)
                                        <option value="{{ $valor }}" @selected(($filtros[$nombre] ?? '') === $valor)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :id="$nombre.'-error'" role="alert" class="mt-2" :messages="$errors->get($nombre)" />
                            </div>
                        @endforeach
                    </div>
                    <x-input-error role="alert" :messages="$errors->get('page')" />
                    <div class="flex flex-wrap items-center gap-3">
                        <x-primary-button class="min-h-11 justify-center">Aplicar filtros</x-primary-button>
                        <a href="{{ route('turnos.agenda-atencion.index', $contextoTemporal) }}"
                           class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 text-sm font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Limpiar filtros</a>
                    </div>
                </fieldset>
            </form>
            </details>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p id="resumen-resultados" role="status" class="text-sm text-gray-600"><span class="font-semibold text-gray-900">{{ $paginacion->total() }} turnos encontrados.</span> Página {{ $paginacion->currentPage() }} de {{ $paginacion->lastPage() }}.</p>
                <nav aria-label="Obtener agenda" class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('turnos.agenda-atencion.index', $contextoTemporal + $filtros + ['page' => $paginacion->currentPage()]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Actualizar agenda</a>
                    <a href="{{ route('turnos.agenda-atencion.imprimir', $contextoTemporal + $filtros) }}" aria-label="Imprimir todos los resultados" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Imprimir</a>
                    <a href="{{ route('turnos.agenda-atencion.pdf', $contextoTemporal + $filtros) }}" aria-label="Descargar PDF de todos los resultados" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Descargar PDF</a>
                </nav>
            </div>
            @if ($turnos === [])
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600" aria-hidden="true"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /></svg></span>
                    @if ($filtros !== [])
                        <p class="font-semibold text-gray-900">No hay turnos que coincidan con los criterios seleccionados.</p>
                        <p class="mt-2 text-sm text-gray-600">Modificá o limpiá los filtros para consultar otros resultados.</p>
                    @else
                    <p class="font-semibold text-gray-900">{{ $esPeriodo ? 'No hay turnos registrados para este período.' : 'No hay turnos registrados para esta fecha.' }}</p>
                    <p class="mt-2 text-sm text-gray-600">{{ $esPeriodo ? 'Elegí otras fechas para consultar otro período.' : 'Seleccioná otra fecha o navegá hacia la jornada anterior o siguiente.' }}</p>
                    @endif
                </div>
            @else
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table class="uc05-tabla w-full text-left text-sm" aria-label="{{ $esPeriodo ? 'Turnos del período' : 'Turnos de la jornada' }}">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                        <tr>
                            <th scope="col">Horario</th>
                            <th scope="col">Paciente</th>
                            <th scope="col">Estado</th>
                            <th scope="col"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @foreach ($turnos as $turno)
                        <tr class="align-top hover:bg-gray-50">
                            <td>
                                <p class="whitespace-nowrap font-semibold tabular-nums text-gray-900"><time datetime="{{ $turno['inicio_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['inicio_at']->format('H:i') }}</time><span aria-hidden="true">–</span><span class="sr-only"> a </span><time datetime="{{ $turno['fin_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['fin_at']->format('H:i') }}</time></p>
                                <p class="mt-1 text-xs leading-5 text-gray-600">{{ $turno['inicio_at']->format('d/m/Y') }}@if (! $turno['inicio_at']->isSameDay($turno['fin_at'])) al {{ $turno['fin_at']->format('d/m/Y') }}@endif · {{ $turno['inicio_at']->diffInMinutes($turno['fin_at']) }} minutos</p>
                            </td>
                            <td>
                                <p class="break-words font-semibold text-gray-900">{{ $turno['paciente']['nombre'] ?? 'Paciente sin nombre registrado' }}</p>
                                <p class="mt-1 text-xs leading-5 text-gray-600">{{ $tipos[$turno['tipo']->value] }}</p>
                            </td>
                            <td>
                                @include('turnos.agenda-atencion.estado', ['estado' => $turno['estado']])
                            </td>
                            <td class="uc05-accion text-right">
                                <a href="{{ route('turnos.agenda-atencion.show', ['turno' => $turno['id']] + $contextoTemporal + $filtros + ['page' => $paginacion->currentPage()]) }}"
                                   x-on:click="abrirDetalle($event)" aria-haspopup="dialog"
                                   aria-label="Ver detalle de turno de {{ $turno['paciente']['nombre'] ?? 'paciente' }}, {{ $turno['inicio_at']->format('d/m/Y H:i') }}"
                                   class="inline-flex min-h-11 items-center justify-center rounded-lg px-2 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Ver detalle de turno</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
            @if ($paginacion->hasPages())
                <nav aria-label="Páginas de resultados" class="flex flex-wrap justify-between gap-3">
                    @if ($paginacion->onFirstPage())
                        <span aria-disabled="true" class="inline-flex min-h-11 items-center px-4 py-2 text-sm text-gray-500">Página anterior</span>
                    @else
                        <a href="{{ $paginacion->previousPageUrl() }}" rel="prev"
                           class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Página anterior</a>
                    @endif
                    @if ($paginacion->hasMorePages())
                        <a href="{{ $paginacion->nextPageUrl() }}" rel="next"
                           class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Página siguiente</a>
                    @else
                        <span aria-disabled="true" class="inline-flex min-h-11 items-center px-4 py-2 text-sm text-gray-500">Página siguiente</span>
                    @endif
                </nav>
            @endif
        </section>
    </div>
</x-app-layout>
