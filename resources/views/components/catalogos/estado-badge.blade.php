@props(['activo', 'textoActivo' => 'Activo', 'textoInactivo' => 'Inactivo'])

<span {{ $attributes->class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-green-100 text-green-700' => $activo, 'bg-red-100 text-red-700' => ! $activo]) }}>
    {{ $activo ? $textoActivo : $textoInactivo }}
</span>
