{{-- resources/views/emails/turnos/confirmacion.blade.php --}}
@php
    use Illuminate\Support\Facades\URL;

    $ttlHours = config('turnos.mail_link_ttl_hours', 24); // o 24 fijo
    $url = URL::temporarySignedRoute(
        'turnos.mail.show',                // ->name('turnos.mail.show')
        now()->addHours($ttlHours),
        ['turno' => $turno->id_turno]     // route-model-binding por id_turno
    );
@endphp

@component('mail::message')
# Recordatorio de turno

Hola **{{ $turno->paciente->name }}**,

Tenés un turno con **{{ $turno->profesional->name }}** el **{{ optional($turno->fecha)->format('d/m/Y') }}**
de **{{ substr((string)$turno->hora_desde,0,5) }}** a **{{ substr((string)$turno->hora_hasta,0,5) }}**.

@component('mail::button', ['url' => $url])
Confirmar o cancelar turno
@endcomponent

> Si ya confirmaste o cancelaste, podés ignorar este mensaje.

Gracias,<br>
{{ config('app.name') }}
@endcomponent

