<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin">
    <title>Oferta de adelanto — KinesioSoft</title>
    <style>
        body{margin:0;background:#f1f5f9;color:#172033;font:1rem/1.6 system-ui,sans-serif}
        main{max-width:40rem;margin:3rem auto;padding:2rem;background:white;border-radius:1rem;box-shadow:0 3px 16px #17203318}
        h1{font-size:1.65rem;line-height:1.3}dt{font-weight:700;margin-top:1rem}dd{margin:0;overflow-wrap:anywhere}
        button{margin-top:1rem;padding:.8rem 1.2rem;border:2px solid #9f1239;border-radius:.4rem;background:#9f1239;color:white;font:inherit;cursor:pointer}
        button:focus-visible{outline:3px solid #172033;outline-offset:4px}p{overflow-wrap:anywhere}
        @media(max-width:44rem){main{margin:1rem;padding:1.2rem}}
    </style>
</head>
<body><main>
    <p>KinesioSoft</p>
    <h1>Oferta de adelanto de turno</h1>
    @if($acceso['estado'] === 'activa')
        <dl>
            <dt>Horario original</dt><dd>{{ $acceso['original'] }}</dd>
            <dt>Horario ofrecido</dt><dd>{{ $acceso['ofrecido'] }}</dd>
            <dt>Kinesióloga</dt><dd>{{ $acceso['profesional'] }}</dd>
            <dt>Vencimiento</dt><dd>{{ $acceso['vencimiento'] }} ({{ $acceso['zona'] }})</dd>
        </dl>
        <p>Tu turno conserva su horario y estado. Abrir esta página no registra una respuesta.</p>
        @if(is_string($csrf))
            <form method="post" action="{{ route('turnos.adelanto.aceptar', [], false) }}">
                <input type="hidden" name="_csrf_oferta" value="{{ $csrf }}">
                <button type="submit" name="accion" value="aceptar">Aceptar adelanto</button>
            </form>
            <form method="post" action="{{ route('turnos.adelanto.rechazar', [], false) }}">
                <input type="hidden" name="_csrf_oferta" value="{{ $csrf }}">
                <button type="submit" name="accion" value="rechazar">Rechazar oferta</button>
            </form>
        @endif
    @elseif($acceso['estado'] === 'aceptada')
        <p role="status">Aceptaste el adelanto. Se confirmó el cambio de horario.</p>
        <dl>
            <dt>Nuevo horario registrado</dt><dd>{{ $acceso['horario_nuevo'] }} ({{ $acceso['zona'] }})</dd>
            <dt>Estado conservado al aceptar</dt><dd>{{ $acceso['estado_turno'] }}</dd>
        </dl>
        @if($acceso['estado_codigo'] === 'PENDIENTE')
            <p>Tu turno quedó Pendiente.</p>
        @endif
    @elseif($acceso['estado'] === 'original_modificado')
        <p role="status">El turno original cambió. El adelanto ofrecido no se aplicó.</p>
    @elseif(in_array($acceso['estado'], ['cupo_no_disponible', 'destino_incompatible', 'cerrada_agenda'], true))
        <p role="status">El horario ofrecido ya no está disponible o no es compatible. No reprogramamos tu turno.</p>
    @elseif($acceso['estado'] === 'traslado_fallido')
        <p role="alert">No pudimos completar el cambio. Volvé a abrir el enlace para consultar el resultado.</p>
    @elseif($acceso['estado'] === 'rechazada')
        <p role="status">La oferta quedó rechazada. Tu turno conserva su horario y estado.</p>
    @elseif($acceso['estado'] === 'vencida')
        <p role="status">La oferta venció. No reprogramamos tu turno.</p>
    @else
        <p role="status">La oferta está cerrada. No reprogramamos tu turno.</p>
    @endif
</main></body>
</html>
