@props(['estado', 'proxima' => false])

@php
    $presentacion = match ($estado) {
        'VIGENTE' => ['Vigente', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'FUTURA' => ['Futura', 'bg-blue-50 text-blue-700 ring-blue-200'],
        'HISTORICA' => ['Histórica', 'bg-gray-100 text-gray-700 ring-gray-200'],
        'ANULADA' => ['Anulada', 'bg-red-50 text-red-700 ring-red-200'],
        default => ['Estado desconocido', 'bg-amber-50 text-amber-700 ring-amber-200'],
    };
@endphp

<span {{ $attributes->class(['inline-flex flex-wrap items-center gap-2']) }}>
    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $presentacion[1] }}">
        {{ $presentacion[0] }}
    </span>

    @if ($proxima)
        <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-200">
            Próxima
        </span>
    @endif
</span>
