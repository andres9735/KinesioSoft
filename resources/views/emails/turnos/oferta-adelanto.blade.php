@component('mail::message')
@php
    // Intentamos obtener la fecha límite desde la oferta (si está disponible)
    $limite = (isset($oferta) && $oferta->fecha_limite_respuesta)
        ? $oferta->fecha_limite_respuesta->timezone(config('app.timezone'))
        : null;

    // Formato: 04/12/2025 a las 17:33 hs
    $limiteTexto = $limite
        ? $limite->format('d/m/Y \a \l\a\s H:i \h\s')
        : null;
@endphp

# Oferta para adelantar tu turno

Hola **{{ $turnoOriginal->paciente->name }}**,

Se liberó un turno con **{{ $turnoOfertado->profesional->name }}** y queremos
ofrecerte **adelantar tu cita**.

### Tu turno ORIGINAL
- Fecha: **{{ optional($turnoOriginal->fecha)->format('d/m/Y') }}**
- Horario: **{{ substr((string) $turnoOriginal->hora_desde, 0, 5) }} – {{ substr((string) $turnoOriginal->hora_hasta, 0, 5) }}**

### Turno DISPONIBLE para adelantar
- Fecha: **{{ optional($turnoOfertado->fecha)->format('d/m/Y') }}**
- Horario: **{{ substr((string) $turnoOfertado->hora_desde, 0, 5) }} – {{ substr((string) $turnoOfertado->hora_hasta, 0, 5) }}**
- Profesional: **{{ $turnoOfertado->profesional->name }}**

@component('mail::button', ['url' => $aceptarUrl])
Aceptar y adelantar mi turno
@endcomponent

@component('mail::button', ['url' => $rechazarUrl])
Mantener mi turno original
@endcomponent

@if($limiteTexto)
> Si no respondés antes de la fecha límite ({{ $limiteTexto }}), la oferta se marcará como *sin respuesta*.
@else
> Si no respondés antes de la fecha límite, la oferta se marcará como *sin respuesta*.
@endif

Gracias,
{{ config('app.name') }}
@endcomponent


