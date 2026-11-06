<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Asistencia a tu turno — KinesioSoft</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font: 1rem/1.6 system-ui, sans-serif; }
        main { max-width: 40rem; margin: 2rem auto; padding: 1.5rem; background: #fff; border-radius: .75rem; }
        h1 { font-size: 1.65rem; line-height: 1.3; }
        h2 { font-size: 1.2rem; }
        dt { font-weight: 700; }
        dd { margin: 0 0 1rem; overflow-wrap: anywhere; }
        .aviso { padding: 1rem; border-left: .3rem solid #1d4ed8; background: #eff6ff; }
        details { margin: 1rem 0; padding: 1rem; border: 1px solid #6b7280; border-radius: .4rem; }
        summary, button { cursor: pointer; }
        button { padding: .7rem 1rem; color: #fff; background: #1e40af; border: 0; border-radius: .3rem; font: inherit; }
        input { margin-right: .5rem; }
        :focus-visible { outline: 3px solid #1d4ed8; outline-offset: 4px; }
        @media (max-width: 42rem) { main { margin: .75rem; padding: 1rem; } }
    </style>
</head>
<body>
<main aria-labelledby="titulo">
    <p>KinesioSoft</p>
    <h1 id="titulo">Asistencia a tu turno</h1>
    @if ($acceso->estado === \App\Services\Turnos\AccesoEnlaceAsistencia::VALIDO)
        <p>Revisá los datos de tu cita. Abrir o actualizar esta página no confirma ni cancela el turno.</p>
        <dl aria-label="Datos esenciales de la cita">
            <dt>Tipo de consulta</dt><dd>{{ $acceso->cita['tipo'] }}</dd>
            <dt>Fecha</dt><dd>{{ $acceso->cita['fecha'] }}</dd>
            <dt>Horario</dt><dd>{{ $acceso->cita['horario'] }}</dd>
            <dt>Kinesióloga</dt><dd>{{ $acceso->cita['kinesiologa'] }}</dd>
            <dt>Estado</dt><dd>{{ $acceso->cita['estado'] }}</dd>
        </dl>
        <section aria-labelledby="acciones">
            <h2 id="acciones">Acciones según el estado y horario del turno</h2>
            <ul>
                <li><strong>Confirmar asistencia:</strong>
                    {{ $acceso->puedeConfirmar ? 'permitido antes del inicio del turno.' : 'tu turno ya está confirmado.' }}
                </li>
                <li><strong>Cancelar turno:</strong>
                    {{ $acceso->puedeCancelar ? 'permitido mientras resten al menos 12 horas.' : 'no disponible online: restan menos de 12 horas. Comunicate con el consultorio.' }}
                </li>
            </ul>
            @if ($csrf && ($acceso->puedeConfirmar || $acceso->puedeCancelar))
                @foreach (['confirmar' => 'Confirmar asistencia', 'cancelar' => 'Cancelar turno'] as $accion => $etiqueta)
                    @if (($accion === 'confirmar' && $acceso->puedeConfirmar) || ($accion === 'cancelar' && $acceso->puedeCancelar))
                        <details>
                            <summary>{{ $etiqueta }}</summary>
                            <form method="POST" action="/turnos/asistencia/decidir">
                                <input type="hidden" name="_csrf_enlace" value="{{ $csrf }}">
                                <input type="hidden" name="accion" value="{{ $accion }}">
                                <fieldset>
                                    <legend>Revisá y confirmá tu decisión</legend>
                                    <p>{{ $accion === 'confirmar' ? 'Confirmar expresa tu intención de concurrir; no registra una atención realizada.' : 'Cancelar libera la reserva de este horario y conserva el historial del turno.' }}</p>
                                    <label for="acepto-{{ $accion }}"><input id="acepto-{{ $accion }}" type="checkbox" name="acepto" value="1" required>Deseo {{ $accion === 'confirmar' ? 'confirmar mi asistencia' : 'cancelar este turno' }}.</label>
                                </fieldset>
                                <button type="submit">{{ $etiqueta }}</button>
                            </form>
                        </details>
                    @endif
                @endforeach
            @endif
            <p class="aviso">Esta consulta no registra ninguna decisión sobre tu asistencia.</p>
        </section>
    @else
        @php
            $mensaje = match ($acceso->estado) {
                'vencido' => 'El enlace está vencido.',
                'revocado' => 'El enlace fue revocado.',
                'programacion_modificada' => 'El enlace corresponde a una programación anterior.',
                'estado_incompatible' => 'El estado actual no permite gestionar asistencia mediante este enlace.',
                default => 'El enlace no es válido.',
            };
        @endphp
        <p role="status" class="aviso">{{ $mensaje }}</p>
        <p>No se realizó ningún cambio. Comunicate con el consultorio para recibir orientación.</p>
    @endif
    @if ($operaciones)
        <section aria-labelledby="recuperacion">
            <h2 id="recuperacion">Si no recibiste la respuesta</h2>
            <p>Consultá el resultado registrado antes de reintentar. Esta consulta no vuelve a ejecutar tu decisión y está disponible durante 15 minutos desde que abriste el enlace.</p>
            <ul>
                @foreach ($operaciones as $accion => $operacion)
                    <li><a href="/turnos/asistencia/resultado?operacion={{ $operacion }}">Consultar resultado de {{ $accion === 'confirmar' ? 'confirmar asistencia' : 'cancelar turno' }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
</body>
</html>
