@props(['estado'])

@php
    $presentacion = match ($estado) {
        'PROXIMO' => ['Próximo', 'bg-blue-50 text-blue-700 ring-blue-200'],
        'EN_CURSO' => ['En curso', 'bg-amber-50 text-amber-700 ring-amber-200'],
        'PASADO' => ['Pasado', 'bg-gray-100 text-gray-700 ring-gray-200'],
        'ANULADO' => ['Anulado', 'bg-red-50 text-red-700 ring-red-200'],
        default => ['Estado desconocido', 'bg-violet-50 text-violet-700 ring-violet-200'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $presentacion[1]]) }}>
    {{ $presentacion[0] }}
</span>
