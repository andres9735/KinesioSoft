<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold leading-tight text-gray-900">Configurar agenda de atención</h1>
            <p class="text-sm text-gray-500">Administrá tus horarios habituales, próximas vigencias y bloqueos excepcionales.</p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <aside class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
                Los cambios en horarios habituales, duración o bloqueos no modifican automáticamente los turnos ya otorgados.
            </aside>

            <section aria-labelledby="acciones-agenda">
                <h2 id="acciones-agenda" class="text-lg font-semibold text-gray-900">Acciones principales</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-3">
                    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h3 class="font-semibold text-gray-900">Horarios habituales</h3>
                        <p class="mt-2 text-sm text-gray-500">Definí una nueva vigencia con su matriz semanal.</p>
                        <a href="{{ route('turnos.agenda.configuraciones.create') }}" class="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            {{ $hayAntecedentes ? 'Nueva configuración' : 'Crear primera configuración' }}
                        </a>
                    </article>

                    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h3 class="font-semibold text-gray-900">Duración estándar</h3>
                        <p class="mt-2 text-sm text-gray-500">Programá una duración diferente con efecto hacia adelante.</p>
                        @if ($puedeCambiarDuracion)
                            <a href="{{ route('turnos.agenda.duracion.create') }}" class="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Cambiar duración</a>
                        @else
                            <p class="mt-4 text-sm font-medium text-gray-600">Primero necesitás una configuración vigente desde la cual conservar la matriz.</p>
                        @endif
                    </article>

                    <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h3 class="font-semibold text-gray-900">Bloqueos excepcionales</h3>
                        <p class="mt-2 text-sm text-gray-500">Registrá feriados, licencias u otras indisponibilidades.</p>
                        <a href="{{ route('turnos.agenda.bloqueos.create') }}" class="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Registrar bloqueo</a>
                    </article>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="configuracion-actual">
                <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                    <h2 id="configuracion-actual" class="text-lg font-semibold text-gray-900">Configuración actual</h2>
                </div>

                @if (! $hayAntecedentes)
                    <div class="px-5 py-10 text-center sm:px-6">
                        <h3 class="text-lg font-semibold text-gray-900">Todavía no configuraste tu agenda</h3>
                        <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500">Creá tu primera configuración para establecer la vigencia, duración y horarios habituales.</p>
                        <a href="{{ route('turnos.agenda.configuraciones.create') }}" class="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Crear primera configuración</a>
                    </div>
                @elseif ($configuracionVigente === null)
                    <div class="px-5 py-8 sm:px-6">
                        <h3 class="text-lg font-semibold text-gray-900">Sin configuración vigente</h3>
                        <p class="mt-2 text-sm text-gray-600">Hoy existe un hueco entre vigencias. Las configuraciones futuras se muestran por separado y no reemplazan la agenda actual.</p>
                    </div>
                @else
                    @php
                        $diasVigentes = $configuracionVigente->franjasHorarias
                            ->map(fn ($franja): int => $franja->dia_semana->value)
                            ->unique()
                            ->count();
                    @endphp
                    <div class="space-y-6 p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <x-agenda.estado-configuracion-badge estado="VIGENTE" />
                                <p class="mt-3 text-sm text-gray-700">
                                    Desde {{ $configuracionVigente->vigencia_desde->format('d/m/Y') }} hasta {{ $configuracionVigente->vigencia_hasta?->format('d/m/Y') ?? 'Sin fecha de fin' }}
                                </p>
                            </div>
                            <a href="{{ route('turnos.agenda.configuraciones.show', ['configuracion' => $configuracionVigente, 'origen' => 'dashboard']) }}" class="inline-flex rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Consultar</a>
                        </div>
                        <dl class="grid gap-4 sm:grid-cols-3">
                            <div><dt class="text-sm text-gray-500">Duración estándar</dt><dd class="mt-1 font-semibold text-gray-900">{{ $configuracionVigente->duracion_turno_minutos }} minutos</dd></div>
                            <div><dt class="text-sm text-gray-500">Días con atención</dt><dd class="mt-1 font-semibold text-gray-900">{{ $diasVigentes }}</dd></div>
                            <div><dt class="text-sm text-gray-500">Franjas semanales</dt><dd class="mt-1 font-semibold text-gray-900">{{ $configuracionVigente->franjasHorarias->count() }}</dd></div>
                        </dl>
                        <div>
                            <h3 class="mb-3 text-base font-semibold text-gray-900">Matriz semanal vigente</h3>
                            <x-agenda.matriz-semanal-lectura :franjas="$configuracionVigente->franjasHorarias" id="vigente" />
                        </div>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="configuraciones-futuras">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="configuraciones-futuras" class="text-lg font-semibold text-gray-900">Configuraciones futuras</h2>
                        <p class="mt-1 text-sm text-gray-500">Cambios programados que todavía no comenzaron.</p>
                    </div>
                    <a href="{{ route('turnos.agenda.configuraciones.create') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Nueva configuración</a>
                </div>

                @if ($configuracionesFuturas->isEmpty())
                    <div class="mt-5 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center">
                        <h3 class="font-semibold text-gray-900">No hay configuraciones futuras programadas</h3>
                        <a href="{{ route('turnos.agenda.configuraciones.create') }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">Nueva configuración</a>
                    </div>
                @else
                    <div class="mt-5 space-y-5">
                        @foreach ($configuracionesFuturas as $configuracion)
                            @php
                                $acciones = $accionesConfiguracionesFuturas->get($configuracion->getKey(), []);
                                $diasAtencion = $configuracion->franjasHorarias
                                    ->map(fn ($franja): int => $franja->dia_semana->value)
                                    ->unique()
                                    ->count();
                            @endphp
                            <article class="rounded-xl border border-gray-200 p-4 sm:p-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <x-agenda.estado-configuracion-badge estado="FUTURA" :proxima="$proximaConfiguracion?->is($configuracion) ?? false" />
                                        <h3 class="mt-3 font-semibold text-gray-900">Desde {{ $configuracion->vigencia_desde->format('d/m/Y') }} hasta {{ $configuracion->vigencia_hasta?->format('d/m/Y') ?? 'Sin fecha de fin' }}</h3>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('turnos.agenda.configuraciones.show', ['configuracion' => $configuracion, 'origen' => 'dashboard']) }}" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Consultar</a>
                                        @if ($acciones['puede_editar'] ?? false)
                                            <a href="{{ route('turnos.agenda.configuraciones.edit', ['configuracion' => $configuracion, 'origen' => 'dashboard']) }}" class="rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">Editar</a>
                                        @endif
                                        @if ($acciones['puede_anular'] ?? false)
                                            <a href="{{ route('turnos.agenda.configuraciones.anular.create', ['configuracion' => $configuracion, 'origen' => 'dashboard']) }}" class="rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Anular</a>
                                        @endif
                                    </div>
                                </div>
                                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div><dt class="text-sm text-gray-500">Duración</dt><dd class="font-semibold text-gray-900">{{ $configuracion->duracion_turno_minutos }} minutos</dd></div>
                                    <div><dt class="text-sm text-gray-500">Días con atención</dt><dd class="font-semibold text-gray-900">{{ $diasAtencion }}</dd></div>
                                    <div><dt class="text-sm text-gray-500">Franjas</dt><dd class="font-semibold text-gray-900">{{ $configuracion->franjasHorarias->count() }}</dd></div>
                                </dl>
                                <x-agenda.matriz-semanal-lectura :franjas="$configuracion->franjasHorarias" :compacta="true" :id="'futura-'.$configuracion->id" class="mt-4" />
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="resumen-historial">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="resumen-historial" class="text-lg font-semibold text-gray-900">Historial de configuraciones</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $cantidadHistorial }} configuración(es) histórica(s) o anulada(s).</p>
                    </div>
                    <a href="{{ route('turnos.agenda.configuraciones.historial') }}" class="inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver historial completo</a>
                </div>
            </section>

            @php
                $labelsFiltros = [
                    'proximos' => 'Próximos',
                    'en_curso' => 'En curso',
                    'pasados' => 'Pasados',
                    'anulados' => 'Anulados',
                    'todos' => 'Todos',
                ];
                $etiquetaVacia = [
                    'proximos' => 'próximos',
                    'en_curso' => 'en curso',
                    'pasados' => 'pasados',
                    'anulados' => 'anulados',
                    'todos' => 'para mostrar',
                ][$filtroBloqueos];
            @endphp
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="bloqueos-agenda">
                <div class="space-y-4 border-b border-gray-100 bg-gray-50 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 id="bloqueos-agenda" class="text-lg font-semibold text-gray-900">Bloqueos de agenda</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ $bloqueos->total() }} resultado(s) para el filtro actual.</p>
                        </div>
                        @if ($cantidadBloqueosEnCurso > 0)
                            <a href="{{ route('turnos.agenda.index', ['filtro' => 'en_curso']) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-900">En curso: {{ $cantidadBloqueosEnCurso }}</a>
                        @endif
                    </div>
                    <form method="GET" action="{{ route('turnos.agenda.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex-1 sm:max-w-xs">
                            <label for="filtro-bloqueos" class="block text-sm font-medium text-gray-700">Filtrar bloqueos</label>
                            <select id="filtro-bloqueos" name="filtro" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($filtrosBloqueos as $filtro)
                                    <option value="{{ $filtro }}" @selected($filtroBloqueos === $filtro)>{{ $labelsFiltros[$filtro] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Aplicar</button>
                    </form>
                </div>

                @if ($bloqueos->isEmpty())
                    <div class="px-5 py-10 text-center sm:px-6">
                        @if (! $hayBloqueos)
                            <h3 class="font-semibold text-gray-900">No hay bloqueos registrados</h3>
                            <p class="mt-2 text-sm text-gray-500">Cuando necesites indicar una indisponibilidad excepcional, podés registrarla desde aquí.</p>
                            <a href="{{ route('turnos.agenda.bloqueos.create') }}" class="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Registrar bloqueo</a>
                        @else
                            <h3 class="font-semibold text-gray-900">No hay bloqueos {{ $etiquetaVacia }}</h3>
                            <p class="mt-2 text-sm text-gray-500">Existen bloqueos registrados, pero ninguno coincide con este filtro.</p>
                            <a href="{{ route('turnos.agenda.index', ['filtro' => 'todos']) }}" class="mt-5 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver todos</a>
                        @endif
                    </div>
                @else
                    <div class="hidden md:block">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Bloqueos de agenda según el filtro {{ $labelsFiltros[$filtroBloqueos] }}</caption>
                            <thead class="border-b border-gray-100 bg-gray-50 text-gray-600">
                                <tr>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Intervalo</th>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Motivo</th>
                                    <th scope="col" class="px-5 py-3 text-left font-semibold sm:px-6">Estado</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold sm:px-6">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($bloqueos as $bloqueo)
                                    @php($estadoBloqueo = $bloqueo->estadoEn($ahora))
                                    <tr>
                                        <td class="px-5 py-4 text-gray-700 sm:px-6"><span class="block">Desde {{ $bloqueo->inicio_at->format('d/m/Y H:i') }}</span><span class="block">Hasta {{ $bloqueo->fin_at->format('d/m/Y H:i') }}</span></td>
                                        <td class="max-w-sm break-words px-5 py-4 text-gray-700 sm:px-6">{{ $bloqueo->motivo }}</td>
                                        <td class="px-5 py-4 sm:px-6"><x-agenda.estado-bloqueo-badge :estado="$estadoBloqueo" /></td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="{{ route('turnos.agenda.bloqueos.show', ['bloqueo' => $bloqueo, 'origen' => 'bloqueos', 'filtro' => $filtroBloqueos, 'bloqueos_page' => $bloqueos->currentPage()]) }}" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200">Consultar</a>
                                                @can('anular', $bloqueo)
                                                    <a href="{{ route('turnos.agenda.bloqueos.anular.create', ['bloqueo' => $bloqueo, 'origen' => 'bloqueos', 'filtro' => $filtroBloqueos, 'bloqueos_page' => $bloqueos->currentPage()]) }}" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">Anular</a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-gray-100 md:hidden">
                        @foreach ($bloqueos as $bloqueo)
                            @php($estadoBloqueo = $bloqueo->estadoEn($ahora))
                            <article class="space-y-3 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-semibold text-gray-900">{{ $bloqueo->motivo }}</h3>
                                    <x-agenda.estado-bloqueo-badge :estado="$estadoBloqueo" class="shrink-0" />
                                </div>
                                <p class="text-sm text-gray-700"><span class="block"><span class="font-medium">Desde:</span> {{ $bloqueo->inicio_at->format('d/m/Y H:i') }}</span><span class="block"><span class="font-medium">Hasta:</span> {{ $bloqueo->fin_at->format('d/m/Y H:i') }}</span></p>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('turnos.agenda.bloqueos.show', ['bloqueo' => $bloqueo, 'origen' => 'bloqueos', 'filtro' => $filtroBloqueos, 'bloqueos_page' => $bloqueos->currentPage()]) }}" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700">Consultar</a>
                                    @can('anular', $bloqueo)
                                        <a href="{{ route('turnos.agenda.bloqueos.anular.create', ['bloqueo' => $bloqueo, 'origen' => 'bloqueos', 'filtro' => $filtroBloqueos, 'bloqueos_page' => $bloqueos->currentPage()]) }}" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">Anular</a>
                                    @endcan
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($bloqueos->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4 sm:px-6">{{ $bloqueos->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
