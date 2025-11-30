<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Respuesta a oferta de turno</title>
</head>
<body>
    @if ($estado === \App\Models\OfertaAdelantoTurno::ESTADO_ACEPTADA)
        <h1>Tu turno fue adelantado correctamente ✅</h1>
        <p>Tu nuevo horario ya quedó registrado en el sistema.</p>

    @elseif ($estado === \App\Models\OfertaAdelantoTurno::ESTADO_RECHAZADA)
        <h1>Mantendremos tu turno original 👍</h1>
        <p>La oferta de adelanto fue rechazada. Tu turno original sigue vigente.</p>

    @elseif ($estado === \App\Models\OfertaAdelantoTurno::ESTADO_EXPIRADA)
        <h1>La oferta ya expiró ⏰</h1>
        <p>La oferta para adelantar tu turno venció.</p>

    @elseif ($estado === \App\Models\OfertaAdelantoTurno::ESTADO_CANCELADA_SISTEMA)
        <h1>No pudimos procesar la oferta ⚠️</h1>
        <p>Ocurrió un problema al procesar esta oferta. Si tenés dudas, contactá al consultorio.</p>

    @elseif ($estado === 'no_encontrada')
        <h1>Oferta no encontrada ❓</h1>
        <p>El enlace que usaste no corresponde a ninguna oferta válida.</p>

    @elseif ($estado === 'accion_invalida')
        <h1>Acción inválida ❌</h1>
        <p>La acción enviada no es válida.</p>

    @else
        <h1>Oferta no disponible</h1>
        <p>Esta oferta ya no está disponible.</p>
    @endif
</body>
</html>
