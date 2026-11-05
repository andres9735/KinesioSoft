<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda de atención</title>
    <style>
        @page { margin: 20mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #111; font-size: 10pt; }
        h1 { font-size: 18pt; } table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 6px; text-align: left; overflow-wrap: anywhere; }
        thead { display: table-header-group; } tr { page-break-inside: avoid; }
        a { color: #222; } .controles { margin-bottom: 20px; }
        a:focus { outline: 2px solid #222; outline-offset: 3px; }
        .controles a { display: inline-block; padding: 12px; }
        @media screen { .tabla-agenda { overflow-x: auto; } .tabla-agenda:focus { outline: 2px solid #222; } }
        @media print { .controles { display: none; } }
    </style>
</head>
<body>
    @php
        $estados = ['PENDIENTE' => 'Pendiente', 'CONFIRMADO' => 'Confirmado', 'EN_ATENCION' => 'En atención', 'ATENDIDO' => 'Atendido', 'AUSENTE' => 'Ausente', 'CANCELADO' => 'Cancelado'];
        $tipos = ['PRIMERA_CONSULTA' => 'Primera consulta', 'SEGUIMIENTO' => 'Seguimiento'];
        $etiquetas = ['paciente' => 'Paciente', 'hora_desde' => 'Hora de inicio desde', 'hora_hasta' => 'Hora de inicio hasta', 'estado' => 'Estado', 'tipo' => 'Tipo'];
    @endphp
    @if (! $paraPdf)
        <nav class="controles" aria-label="Opciones de impresión">
            <a href="{{ route('turnos.agenda-atencion.index', $parametros) }}">Volver a la agenda</a>
            <p>Usá la opción Imprimir del navegador (Ctrl+P) para imprimir esta representación.</p>
        </nav>
    @endif
    <main>
        <h1>Agenda de atención</h1>
        <p>{{ $esPeriodo ? 'Período' : 'Jornada' }}: {{ $fecha->format('d/m/Y') }}@if ($esPeriodo) al {{ $hasta->format('d/m/Y') }}@endif.</p>
        <p>Generada el {{ $generadoEn->format('d/m/Y H:i:s') }} (hora local del consultorio, {{ $generadoEn->timezoneName }}).</p>
        <p>Esta representación corresponde al momento de emisión. Una nueva consulta muestra la programación vigente.</p>
        @if ($filtros !== [])
            <dl>
                @foreach ($filtros as $nombre => $valor)
                    <dt>{{ $etiquetas[$nombre] }}</dt><dd>{{ $nombre === 'estado' ? $estados[$valor] : ($nombre === 'tipo' ? $tipos[$valor] : $valor) }}</dd>
                @endforeach
            </dl>
        @endif
        @if ($resultados === [])
            <p>{{ $filtros !== [] ? 'No hay turnos que coincidan con los criterios seleccionados.' : ($esPeriodo ? 'No hay turnos registrados para este período.' : 'No hay turnos registrados para esta fecha.') }}</p>
        @else
            {{-- Tablas acotadas evitan calcular una única grilla extensa en Dompdf; no se recortan resultados. --}}
            @foreach ($paraPdf ? array_chunk($resultados, 25) : [$resultados] as $bloque)
            @php($idTabla = $loop->first ? 'titulo-tabla-agenda' : 'titulo-tabla-agenda-'.$loop->index)
            <div class="tabla-agenda" role="region" aria-labelledby="{{ $idTabla }}" tabindex="0">
            <table>
                <caption id="{{ $idTabla }}">Turnos autorizados, ordenados por inicio. Horarios locales.</caption>
                <thead><tr><th scope="col">Paciente</th><th scope="col">Tipo</th><th scope="col">Inicio</th><th scope="col">Fin</th><th scope="col">Duración</th><th scope="col">Profesional</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                    @foreach ($bloque as $turno)
                        <tr>
                            <td>{{ $turno['paciente']['nombre'] ?? 'Paciente sin nombre registrado' }}</td>
                            <td>{{ $tipos[$turno['tipo']->value] }}</td>
                            <td>{{ $turno['inicio_at']->format('d/m/Y H:i') }}</td>
                            <td>{{ $turno['fin_at']->format('d/m/Y H:i') }}</td>
                            <td>{{ $turno['inicio_at']->diffInMinutes($turno['fin_at']) }} minutos</td>
                            <td>{{ $turno['kinesiologa']['nombre'] ?? 'Profesional sin nombre registrado' }}</td>
                            <td>{{ $estados[$turno['estado']->value] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            @endforeach
        @endif
    </main>
</body>
</html>
