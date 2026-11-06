<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Recordatorio de turno</title>
</head>
<body style="margin:0;padding:20px;font-family:Arial,sans-serif;color:#17242d;background:#f4f7f9">
    <main style="max-width:600px;margin:auto;padding:24px;background:white;border-radius:12px">
        <h1 style="font-size:24px">Recordatorio de tu turno</h1>
        <p>Te recordamos tu próxima cita en KinesioSoft.</p>
        <dl>
            <dt>Tipo de consulta</dt><dd>{{ $tipo }}</dd>
            <dt>Fecha local</dt><dd>{{ $fecha }}</dd>
            <dt>Horario local</dt><dd>{{ $horario }}</dd>
            <dt>Kinesióloga</dt><dd>{{ $profesional }}</dd>
            <dt>Estado</dt><dd>{{ $estado }}</dd>
        </dl>
        @if ($confirmar)
            <p>Podés confirmar tu asistencia antes del inicio del turno.</p>
            <p><a href="{{ $url }}" rel="noreferrer" style="display:inline-block;padding:12px">Confirmar asistencia</a></p>
        @endif
        @if ($cancelar)
            <p><a href="{{ $url }}" rel="noreferrer" style="display:inline-block;padding:12px">Cancelar turno</a></p>
        @else
            <p>La cancelación online requiere al menos 12 horas de anticipación. Para consultar por una cancelación, contactá al consultorio.</p>
        @endif
        @if ($confirmar || $cancelar)
            <p>El enlace permite revisar la cita. Abrirlo no confirma ni cancela el turno: cada operación requiere una decisión expresa y se verifica nuevamente al realizarla.</p>
        @else
            <p>Este recordatorio es informativo; no requiere una nueva confirmación.</p>
        @endif
        <p>Confirmar asistencia no acredita la presencia física. Para otras consultas, contactá al consultorio por los canales habituales.</p>
    </main>
</body>
</html>
