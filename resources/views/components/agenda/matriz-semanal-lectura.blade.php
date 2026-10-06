@props(['franjas', 'compacta' => false])

@php
    $dias = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
    $coleccion = collect($franjas);
@endphp

<div {{ $attributes->class(['grid gap-3 sm:grid-cols-2', 'lg:grid-cols-3' => $compacta]) }}>
    @foreach ($dias as $numero => $nombre)
        @php
            $franjasDelDia = $coleccion
                ->filter(fn ($franja): bool => $franja->dia_semana->value === $numero)
                ->sortBy(fn ($franja): string => (string) $franja->hora_inicio);
        @endphp

        <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4" aria-labelledby="dia-agenda-{{ $numero }}-{{ $attributes->get('id', 'matriz') }}">
            <h3 id="dia-agenda-{{ $numero }}-{{ $attributes->get('id', 'matriz') }}" class="text-sm font-semibold text-gray-900">
                {{ $nombre }}
            </h3>

            @if ($franjasDelDia->isEmpty())
                <p class="mt-2 text-sm text-gray-500">Sin atención</p>
            @else
                <ul class="mt-2 flex flex-wrap gap-2" aria-label="Franjas de {{ $nombre }}">
                    @foreach ($franjasDelDia as $franja)
                        <li class="rounded-full bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-200">
                            <time datetime="{{ substr((string) $franja->hora_inicio, 0, 5) }}">{{ substr((string) $franja->hora_inicio, 0, 5) }}</time>
                            a
                            <time datetime="{{ substr((string) $franja->hora_fin, 0, 5) }}">{{ substr((string) $franja->hora_fin, 0, 5) }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>
