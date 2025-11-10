@component('mail::message')
# Recordatorio de turno

Hola **{{ $turno->paciente->name }}**,

Tenés un turno con **{{ $turno->profesional->name }}** el **{{ $turno->fecha?->format('d/m/Y') }}**
de **{{ substr((string)$turno->hora_desde,0,5) }}** a **{{ substr((string)$turno->hora_hasta,0,5) }}**.

@component('mail::button', ['url' => $confirmUrl])
Confirmar asistencia
@endcomponent

@component('mail::button', ['url' => $cancelUrl])
Cancelar turno
@endcomponent

> Si ya confirmaste o cancelaste, podés ignorar este mensaje.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
