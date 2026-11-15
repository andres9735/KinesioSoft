<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda de atención</title>
    <style>
        @page { margin: 13mm 12mm 18mm; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #111827; font-size: 9pt; line-height: 1.45; }
        .marca { margin: 0 0 5pt; color: #4f46e5; font-size: 8pt; font-weight: bold; letter-spacing: 1.5pt; }
        h1 { margin: 0 0 6pt; font-size: 22pt; line-height: 1.2; }
        .periodo { margin: 0 0 8pt; font-size: 13pt; font-weight: bold; }
        .emision { margin: 0; color: #4b5563; font-size: 8pt; }
        .nota { margin: 8pt 0 14pt; padding: 8pt 10pt; border-left: 3pt solid #6366f1; background: #f3f4f6; color: #4b5563; font-size: 8pt; }
        .filtros { margin: 0 0 12pt; padding: 8pt 10pt; border: 1pt solid #e5e7eb; }
        .filtros dt, .filtros dd { display: inline; margin: 0; }
        .filtros dt { font-weight: bold; }
        .filtros dd { margin-right: 12pt; }
        .total { margin: 0 0 8pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 12pt; }
        caption { padding: 0 0 5pt; color: #4b5563; text-align: left; font-size: 8pt; }
        th, td { padding: 7pt 6pt; border-bottom: 0.6pt solid #d1d5db; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background: #eef2ff; border-top: 1pt solid #6366f1; border-bottom: 1pt solid #a5b4fc; color: #312e81; font-size: 8pt; }
        th:nth-child(1) { width: 20%; } th:nth-child(2) { width: 12%; }
        th:nth-child(3), th:nth-child(4) { width: 13%; }
        th:nth-child(5) { width: 10%; } th:nth-child(6) { width: 20%; } th:nth-child(7) { width: 12%; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        thead { display: table-header-group; } tr { page-break-inside: avoid; }
        .vacio { margin: 18pt 0; padding: 28pt 16pt; border: 1pt dashed #9ca3af; background: #f9fafb; text-align: center; }
        .vacio strong { font-size: 12pt; } .vacio p { color: #4b5563; }
        a { color: #4338ca; } .controles { margin-bottom: 20px; }
        a:focus { outline: 2px solid #4f46e5; outline-offset: 3px; }
        .controles a { display: inline-block; padding: 12px; }
        @media screen { body { max-width: 1120px; margin: 24px auto; padding: 0 16px; } .tabla-agenda { overflow-x: auto; } .tabla-agenda:focus { outline: 2px solid #4f46e5; } table { min-width: 850px; } }
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
        <p class="marca">KINESIOSOFT</p>
        <h1>Agenda de atención</h1>
        <p class="periodo">{{ $esPeriodo ? 'Período' : 'Jornada' }}: {{ $fecha->format('d/m/Y') }}@if ($esPeriodo) al {{ $hasta->format('d/m/Y') }}@endif.</p>
        <p class="emision">Generada el {{ $generadoEn->format('d/m/Y H:i:s') }} (hora local del consultorio, {{ $generadoEn->timezoneName }}).</p>
        <p class="nota">Esta representación corresponde al momento de emisión. Una nueva consulta muestra la programación vigente.</p>
        @if ($filtros !== [])
            <dl class="filtros">
                @foreach ($filtros as $nombre => $valor)
                    <dt>{{ $etiquetas[$nombre] }}</dt><dd>{{ $nombre === 'estado' ? $estados[$valor] : ($nombre === 'tipo' ? $tipos[$valor] : $valor) }}</dd>
                @endforeach
            </dl>
        @endif
        <p class="total">{{ count($resultados) }} turnos · Todos los resultados autorizados</p>
        @if ($resultados === [])
            <div class="vacio"><strong>{{ $filtros !== [] ? 'No hay turnos que coincidan con los criterios seleccionados.' : ($esPeriodo ? 'No hay turnos registrados para este período.' : 'No hay turnos registrados para esta fecha.') }}</strong><p>Consultá otra fecha, período o combinación de filtros desde la agenda.</p></div>
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
