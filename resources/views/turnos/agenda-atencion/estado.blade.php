@php
    $etiquetaEstado = match ($estado->value) {
        'PENDIENTE' => 'Pendiente', 'CONFIRMADO' => 'Confirmado', 'EN_ATENCION' => 'En atención',
        'ATENDIDO' => 'Atendido', 'AUSENTE' => 'Ausente', 'CANCELADO' => 'Cancelado',
    };
@endphp
<span class="uc05-estado uc05-estado--{{ strtolower($estado->value) }}">{{ $etiquetaEstado }}</span>
