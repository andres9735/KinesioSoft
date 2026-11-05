<x-app-layout>
    @php
        $estados = ['PENDIENTE' => 'Pendiente', 'CONFIRMADO' => 'Confirmado', 'EN_ATENCION' => 'En atención', 'ATENDIDO' => 'Atendido', 'AUSENTE' => 'Ausente', 'CANCELADO' => 'Cancelado'];
        $tipos = ['PRIMERA_CONSULTA' => 'Primera consulta', 'SEGUIMIENTO' => 'Seguimiento'];
    @endphp
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-gray-900">Agenda de atención</h1>
    </x-slot>

    <div class="py-8">
        <section class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8" aria-labelledby="jornada-agenda">
            <div class="space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                <div>
                    <h2 id="jornada-agenda" class="text-xl font-semibold text-gray-900">
                        @if ($esPeriodo)
                            Período del <time datetime="{{ $fecha->toDateString() }}">{{ $fecha->format('d/m/Y') }}</time>
                            al <time datetime="{{ $hasta->toDateString() }}">{{ $hasta->format('d/m/Y') }}</time>
                        @else
                            Jornada del <time datetime="{{ $fecha->toDateString() }}">{{ $fecha->format('d/m/Y') }}</time>
                        @endif
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Horarios locales del consultorio. Turnos ordenados por hora de inicio.</p>
                </div>

                @if (! $esPeriodo)
                <nav aria-label="Navegación entre jornadas" class="flex flex-wrap gap-3">
                    <a href="{{ route('turnos.agenda-atencion.index', ['fecha' => $fecha->subDay()->toDateString()] + $filtros) }}" rel="prev"
                       aria-label="Jornada anterior, {{ $fecha->subDay()->format('d/m/Y') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Día anterior
                    </a>
                    <a href="{{ route('turnos.agenda-atencion.index', ['fecha' => $fecha->addDay()->toDateString()] + $filtros) }}" rel="next"
                       aria-label="Jornada siguiente, {{ $fecha->addDay()->format('d/m/Y') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Día siguiente
                    </a>
                </nav>
                @endif

                <form method="GET" action="{{ route('turnos.agenda-atencion.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @foreach ($filtros as $nombre => $valor)
                        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                    @endforeach
                    <div class="min-w-0 sm:max-w-xs">
                        <x-input-label for="fecha" value="Fecha de la jornada" />
                        <x-text-input id="fecha" name="fecha" type="date" :value="$fecha->toDateString()" required
                            class="mt-1 min-h-11 w-full" :aria-invalid="$errors->has('fecha') ? 'true' : 'false'"
                            :aria-describedby="$errors->has('fecha') ? 'fecha-ayuda fecha-error' : 'fecha-ayuda'" />
                        <p id="fecha-ayuda" class="mt-1 text-sm text-gray-600">Podés consultar fechas pasadas o futuras.</p>
                        @if ($errors->has('fecha'))
                            <x-input-error id="fecha-error" role="alert" class="mt-2" :messages="$errors->get('fecha')" />
                        @endif
                    </div>
                    <x-primary-button class="min-h-11 justify-center self-start">Consultar fecha</x-primary-button>
                </form>

                <form method="GET" action="{{ route('turnos.agenda-atencion.index') }}">
                    @foreach ($filtros as $nombre => $valor)
                        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                    @endforeach
                    <fieldset class="min-w-0 space-y-3 border-t border-gray-200 pt-4">
                        <legend class="font-semibold text-gray-900">Consultar un período</legend>
                        <div class="grid gap-3 sm:grid-cols-3 sm:items-start">
                            @foreach (['desde' => ['Fecha inicial', $fecha], 'hasta' => ['Fecha final', $hasta]] as $nombre => [$etiqueta, $valor])
                                <div class="min-w-0">
                                    <x-input-label :for="$nombre" :value="$etiqueta" />
                                    <x-text-input :id="$nombre" :name="$nombre" type="date" :value="$valor->toDateString()" required
                                        class="mt-1 min-h-11 w-full" :aria-invalid="$errors->has($nombre) ? 'true' : 'false'"
                                        :aria-describedby="$errors->has($nombre) ? $nombre.'-error' : null" />
                                    <x-input-error :id="$nombre.'-error'" role="alert" class="mt-2" :messages="$errors->get($nombre)" />
                                </div>
                            @endforeach
                            <x-primary-button class="min-h-11 justify-center sm:mt-6">Consultar período</x-primary-button>
                        </div>
                    </fieldset>
                </form>
            </div>

            <form method="GET" action="{{ route('turnos.agenda-atencion.index') }}" class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                @foreach ($contextoTemporal as $nombre => $valor)
                    <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                @endforeach
                <fieldset class="min-w-0 space-y-4">
                    <legend class="font-semibold text-gray-900">Filtrar turnos</legend>
                    <p id="filtros-ayuda" class="text-sm text-gray-600">Buscá por parte del nombre o apellido del paciente. El horario corresponde al inicio del turno, en hora local.</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (['paciente' => ['Buscar paciente', 'search'], 'hora_desde' => ['Hora de inicio desde (inclusive)', 'time'], 'hora_hasta' => ['Hora de inicio hasta (inclusive)', 'time']] as $nombre => [$etiqueta, $tipoInput])
                            <div class="min-w-0">
                                <x-input-label :for="$nombre" :value="$etiqueta" />
                                <x-text-input :id="$nombre" :name="$nombre" :type="$tipoInput" :value="$filtros[$nombre] ?? ''"
                                    class="mt-1 min-h-11 w-full" :aria-invalid="$errors->has($nombre) ? 'true' : 'false'"
                                    :aria-describedby="$errors->has($nombre) ? 'filtros-ayuda '.$nombre.'-error' : 'filtros-ayuda'" />
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

            <p class="text-sm text-gray-600">{{ $paginacion->total() }} turnos encontrados. Página {{ $paginacion->currentPage() }} de {{ $paginacion->lastPage() }}.</p>
            @if ($turnos === [])
                <div class="rounded-2xl bg-white px-6 py-10 text-center shadow-sm">
                    @if ($filtros !== [])
                        <p class="font-semibold text-gray-900">No hay turnos que coincidan con los criterios seleccionados.</p>
                        <p class="mt-2 text-sm text-gray-600">Modificá o limpiá los filtros para consultar otros resultados.</p>
                    @else
                    <p class="font-semibold text-gray-900">{{ $esPeriodo ? 'No hay turnos registrados para este período.' : 'No hay turnos registrados para esta fecha.' }}</p>
                    <p class="mt-2 text-sm text-gray-600">Seleccioná otra fecha o navegá hacia la jornada anterior o siguiente.</p>
                    @endif
                </div>
            @else
                <ol aria-label="{{ $esPeriodo ? 'Turnos del período' : 'Turnos de la jornada' }}" class="space-y-4">
                    @foreach ($turnos as $turno)
                        <li class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-3 sm:p-6">
                            <dl class="space-y-2 border-l-4 border-indigo-500 pl-4 text-sm text-gray-700">
                                <div>
                                    <dt class="font-medium">Inicio</dt>
                                    <dd><time datetime="{{ $turno['inicio_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['inicio_at']->format('d/m/Y H:i') }}</time></dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Fin</dt>
                                    <dd><time datetime="{{ $turno['fin_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['fin_at']->format('d/m/Y H:i') }}</time></dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Duración</dt>
                                    <dd>{{ $turno['inicio_at']->diffInMinutes($turno['fin_at']) }} minutos</dd>
                                </div>
                            </dl>
                            <div class="min-w-0 space-y-3 sm:col-span-2">
                                <h3 class="break-words text-lg font-semibold text-gray-900">{{ $turno['paciente']['nombre'] ?? 'Paciente sin nombre registrado' }}</h3>
                                <dl class="space-y-2 text-sm text-gray-700">
                                    <div>
                                        <dt class="font-medium">Tipo de consulta</dt>
                                        <dd>{{ $tipos[$turno['tipo']->value] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium">Profesional</dt>
                                        <dd class="break-words">{{ $turno['kinesiologa']['nombre'] ?? 'Profesional sin nombre registrado' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium">Estado</dt>
                                        <dd class="mt-1 inline-flex rounded-full border border-gray-300 bg-gray-50 px-3 py-1 font-semibold text-gray-800">{{ $estados[$turno['estado']->value] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </li>
                    @endforeach
                </ol>
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
