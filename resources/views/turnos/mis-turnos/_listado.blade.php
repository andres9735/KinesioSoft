@php
    $tipos = [
        \App\Enums\TipoTurnoEnum::PRIMERA_CONSULTA->value => 'Primera consulta',
        \App\Enums\TipoTurnoEnum::SEGUIMIENTO->value => 'Seguimiento',
    ];
    $estados = [
        \App\Enums\EstadoTurnoEnum::PENDIENTE->value => 'Pendiente',
        \App\Enums\EstadoTurnoEnum::CONFIRMADO->value => 'Confirmado',
        \App\Enums\EstadoTurnoEnum::EN_ATENCION->value => 'En atención',
        \App\Enums\EstadoTurnoEnum::ATENDIDO->value => 'Atendido',
        \App\Enums\EstadoTurnoEnum::AUSENTE->value => 'Ausente',
        \App\Enums\EstadoTurnoEnum::CANCELADO->value => 'Cancelado',
    ];
@endphp

<section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100" aria-labelledby="{{ $seccion }}-titulo">
    <div class="border-b border-gray-100 bg-gray-50 px-5 py-5 sm:px-6">
        <h2 id="{{ $seccion }}-titulo" class="text-lg font-semibold text-gray-900">{{ $titulo }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ $turnos->total() }} {{ $turnos->total() === 1 ? 'turno' : 'turnos' }}</p>
    </div>

    @if ($turnos->total() === 0)
        <p class="px-5 py-10 text-center text-sm text-gray-600">{{ $vacio }}</p>
    @elseif ($turnos->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-gray-600">No hay turnos en esta página.</p>
    @else
        <div class="hidden md:block">
            <table class="min-w-full text-sm">
                <caption class="sr-only">{{ $titulo }}</caption>
                <thead class="border-b border-gray-100 bg-gray-50 text-gray-600">
                    <tr>
                        @foreach (['Tipo', 'Profesional', 'Fecha', 'Horario', 'Estado'] as $columna)
                            <th scope="col" class="px-5 py-3 text-left font-semibold">{{ $columna }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($turnos as $turno)
                        <tr @class(['bg-indigo-50' => $turno->id === $turnoDestacadoId])>
                            <td class="px-5 py-4 text-gray-900">
                                {{ $tipos[$turno->tipo->value] }}
                                @if ($turno->id === $turnoDestacadoId)
                                    <span class="mt-1 block text-xs font-semibold text-indigo-700">Turno destacado</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-gray-700">{{ $turno->kinesiologa->user->name }} {{ $turno->kinesiologa->user->apellido }}</td>
                            <td class="px-5 py-4 text-gray-700">{{ $turno->inicio_at->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700">{{ $turno->inicio_at->format('H:i') }} – {{ $turno->fin_at->format('H:i') }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $estados[$turno->estado->value] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-gray-100 md:hidden">
            @foreach ($turnos as $turno)
                <article @class(['space-y-3 p-5', 'bg-indigo-50' => $turno->id === $turnoDestacadoId])>
                    <h3 class="font-semibold text-gray-900">{{ $tipos[$turno->tipo->value] }}</h3>
                    @if ($turno->id === $turnoDestacadoId)
                        <p class="text-xs font-semibold text-indigo-700">Turno destacado</p>
                    @endif
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">Profesional</dt><dd class="break-words font-medium text-gray-900">{{ $turno->kinesiologa->user->name }} {{ $turno->kinesiologa->user->apellido }}</dd></div>
                        <div><dt class="text-gray-500">Fecha</dt><dd class="font-medium text-gray-900">{{ $turno->inicio_at->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-gray-500">Horario</dt><dd class="font-medium text-gray-900">{{ $turno->inicio_at->format('H:i') }} – {{ $turno->fin_at->format('H:i') }}</dd></div>
                        <div><dt class="text-gray-500">Estado</dt><dd class="font-medium text-gray-900">{{ $estados[$turno->estado->value] }}</dd></div>
                    </dl>
                </article>
            @endforeach
        </div>
    @endif

    @if ($turnos->hasPages())
        <div class="border-t border-gray-100 px-5 py-4 sm:px-6" role="region" aria-label="Paginación de {{ $titulo }}">{{ $turnos->links() }}</div>
    @endif
</section>
