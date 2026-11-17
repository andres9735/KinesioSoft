<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Oferta de adelanto de turno</title></head>
<body>
    <h1>Podés adelantar tu turno</h1>
    <p>Kinesióloga: {{ $profesional }}</p>
    <p>Horario actual: {{ $actual }}</p>
    <p>Horario ofrecido: {{ $ofrecido }}</p>
    <p>La oferta vence el {{ $vencimiento }} ({{ $zona }}).</p>
    <p>Tu turno conserva su horario y estado hasta que aceptes el adelanto.</p>
    <p><a href="{{ $url }}">Consultar oferta de adelanto</a></p>
</body>
</html>
