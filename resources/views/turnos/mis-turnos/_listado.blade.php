@php
    $tipos = [
        \App\Enums\TipoTurnoEnum::PRIMERA_CONSULTA->value => 'Primera consulta',
        \App\Enums\TipoTurnoEnum::SEGUIMIENTO->value => 'Seguimiento',
    ];
    $estados = [
        \App\Enums\EstadoTurnoEnum::PENDIENTE->value => ['texto' => 'Pendiente', 'clases' => 'bg-amber-50 text-amber-800'],
        \App\Enums\EstadoTurnoEnum::CONFIRMADO->value => ['texto' => 'Confirmado', 'clases' => 'bg-emerald-100 text-emerald-800'],
        \App\Enums\EstadoTurnoEnum::EN_ATENCION->value => ['texto' => 'En atención', 'clases' => 'bg-blue-100 text-blue-800'],
        \App\Enums\EstadoTurnoEnum::ATENDIDO->value => ['texto' => 'Atendido', 'clases' => 'bg-indigo-100 text-gray-600'],
        \App\Enums\EstadoTurnoEnum::AUSENTE->value => ['texto' => 'Ausente', 'clases' => 'bg-red-50 text-red-700'],
        \App\Enums\EstadoTurnoEnum::CANCELADO->value => ['texto' => 'Cancelado', 'clases' => 'bg-gray-100 text-gray-600'],
    ];
@endphp

<section class="min-w-0 space-y-4" aria-labelledby="{{ $seccion }}-titulo">
    <h2 id="{{ $seccion }}-titulo" class="flex flex-wrap items-center gap-2 text-xl font-semibold text-gray-900">
        {{ $titulo }}
        <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-sm text-indigo-900" aria-label="{{ $turnos->total() }} {{ $turnos->total() === 1 ? 'turno' : 'turnos' }}">{{ $turnos->total() }}</span>
    </h2>

    @if ($turnos->total() === 0)
        <p class="px-5 py-10 text-center text-sm text-gray-600">{{ $vacio }}</p>
    @elseif ($turnos->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-gray-600">No hay turnos en esta página.</p>
    @else
        <ul class="space-y-3">
            @foreach ($turnos as $turno)
                <li>
                    <article @class(['flex min-w-0 flex-col gap-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6 md:flex-row md:items-center md:justify-between', 'ring-2 ring-indigo-200' => $turno->id === $turnoDestacadoId])>
                        <div class="flex min-w-0 items-center gap-4">
                            <span aria-hidden="true" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    @if ($seccion === 'historicos')
                                        <path d="M3 11a9 9 0 1 1 3 7M3 4v7h7m2-4v5l3 2"/>
                                    @elseif ($turno->tipo === \App\Enums\TipoTurnoEnum::PRIMERA_CONSULTA)
                                        <path d="M9 7V4h6v3M3 7h18v14H3V7Zm9 4v6m-3-3h6"/>
                                    @else
                                        <path d="M2 12h5l3-6 4 12 3-6h5"/>
                                    @endif
                                </svg>
                            </span>
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-medium text-gray-900">{{ $tipos[$turno->tipo->value] }}</h3>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $estados[$turno->estado->value]['clases'] }}">
                                        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $estados[$turno->estado->value]['texto'] }}
                                    </span>
                                </div>
                                <p @class(['break-words text-gray-900', 'text-lg font-semibold' => $seccion === 'proximos', 'text-sm' => $seccion === 'historicos'])>{{ $turno->kinesiologa->user->name }} {{ $turno->kinesiologa->user->apellido }}</p>
                                @if ($turno->id === $turnoDestacadoId)
                                    <p class="text-xs font-semibold text-indigo-700">Turno destacado</p>
                                @endif
                            </div>
                        </div>
                        <dl @class(['grid min-w-0 gap-4 text-sm sm:grid-cols-2 md:shrink-0', 'rounded-xl bg-indigo-50 p-4 text-indigo-950' => $seccion === 'proximos', 'text-gray-600' => $seccion === 'historicos'])>
                            <div class="flex items-center gap-2">
                                <dt class="sr-only">Fecha</dt>
                                <dd class="flex items-center gap-2 font-medium">
                                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                                    <time datetime="{{ $turno->inicio_at->format('Y-m-d') }}">{{ $turno->inicio_at->format('d/m/Y') }}</time>
                                </dd>
                            </div>
                            <div class="flex items-center gap-2 sm:border-l sm:border-indigo-100 sm:pl-4">
                                <dt class="sr-only">Horario</dt>
                                <dd class="flex items-center gap-2 font-medium">
                                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
                                    <span>{{ $turno->inicio_at->format('H:i') }} – {{ $turno->fin_at->format('H:i') }} hs</span>
                                </dd>
                            </div>
                        </dl>
                    </article>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($turnos->hasPages())
        <div class="border-t border-gray-100 px-5 py-4 sm:px-6" role="region" aria-label="Paginación de {{ $titulo }}">{{ $turnos->links() }}</div>
    @endif
</section>
