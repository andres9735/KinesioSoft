@component('mail::message')
# Oferta para adelantar tu turno

Hola **{{ $turnoOriginal->paciente->name }}**,

Se liberó un turno con **{{ $turnoOfertado->profesional->name }}** y queremos
ofrecerte **adelantar tu cita**.

### Tu turno ORIGINAL
- Fecha: **{{ optional($turnoOriginal->fecha)->format('d/m/Y') }}**
- Horario: **{{ substr((string)$turnoOriginal->hora_desde,0,5) }} – {{ substr((string)$turnoOriginal->hora_hasta,0,5) }}**

### Turno DISPONIBLE para adelantar
- Fecha: **{{ optional($turnoOfertado->fecha)->format('d/m/Y') }}**
- Horario: **{{ substr((string)$turnoOfertado->hora_desde,0,5) }} – {{ substr((string)$turnoOfertado->hora_hasta,0,5) }}**
- Profesional: **{{ $turnoOfertado->profesional->name }}**

@component('mail::button', ['url' => $aceptarUrl])
Aceptar y adelantar mi turno
@endcomponent

@component('mail::button', ['url' => $rechazarUrl])
Mantener mi turno original
@endcomponent

> Si no respondés antes de la fecha límite,
> la oferta se marcará como *sin respuesta* y podremos ofrecérsela a otro paciente.

Gracias,
{{ config('app.name') }}
@endcomponent
