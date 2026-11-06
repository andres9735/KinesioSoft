<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Resultado de asistencia — KinesioSoft</title>
    <style>
        body { margin: 0; background: #f3f4f6; color: #111827; font: 1rem/1.6 system-ui, sans-serif; }
        main { max-width: 38rem; margin: 2rem auto; padding: 1.5rem; background: white; border-radius: .75rem; }
        h1 { font-size: 1.6rem; } a { color: #1e40af; } :focus-visible { outline: 3px solid #1d4ed8; outline-offset: 4px; }
        @media (max-width: 42rem) { main { margin: .75rem; padding: 1rem; } }
    </style>
</head>
<body>
<main aria-labelledby="titulo">
    <p>KinesioSoft</p>
    <h1 id="titulo">Resultado de tu decisión</h1>
    @php
        $mensaje = match ($codigo) {
            'exito' => $replay ? 'La operación ya estaba registrada. No se aplicó otra modificación.' : ($accion === 'confirmar' ? 'Tu asistencia quedó confirmada.' : 'El turno quedó cancelado.'),
            'csrf' => 'No pudimos verificar esta solicitud. Reabrí el enlace recibido por correo antes de decidir.',
            'limite' => 'Se recibieron demasiadas solicitudes. Esperá un minuto antes de volver a intentar.',
            'vencido' => 'La autorización venció o el turno ya alcanzó su inicio. Reabrí el enlace recibido por correo o comunicate con el consultorio.',
            'revocado' => 'El enlace fue revocado y no permite nuevas operaciones.',
            'programacion_modificada' => 'La programación o autorización cambió. Este acceso anterior no permite nuevas operaciones.',
            'estado_o_programacion' => 'El estado o la programación actual no permiten esta operación. Si ya enviaste una decisión, verificá el estado actual antes de reintentar.',
            'cancelacion_fuera_de_plazo' => 'Restan menos de 12 horas. No se permite cancelar online; comunicate con el consultorio.',
            'decision_invalida' => 'Seleccioná una operación disponible y confirmá expresamente tu decisión.',
            'error_tecnico' => 'No pudimos comprobar el resultado. Revisá el estado actual antes de reintentar; no se presume un resultado exitoso o fallido.',
            'sin_resultado' => 'No hay un resultado consolidado verificable para esta operación. No se presume éxito ni fracaso. Consultá nuevamente antes de reintentar o comunicate con el consultorio.',
            default => 'El enlace no es válido. Reabrí el enlace recibido por correo o comunicate con el consultorio.',
        };
    @endphp
    <p role="status">{{ $mensaje }}</p>
    <p>Actualizar esta página no vuelve a ejecutar la operación.</p>
    <p><a href="/turnos/asistencia">Revisar el estado actual</a></p>
    <p>Si necesitás ayuda, comunicate con el consultorio.</p>
</main>
</body>
</html>
