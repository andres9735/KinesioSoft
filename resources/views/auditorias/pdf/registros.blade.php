@php
    $formatearFechaFiltro = static function (?string $fecha): string {
        if ($fecha === null || $fecha === '') {
            return 'Todos';
        }

        return \Carbon\Carbon::createFromFormat(
            'Y-m-d',
            $fecha
        )->format('d/m/Y');
    };

    $formatearEvento = static function (?string $evento): string {
        if ($evento === null || $evento === '') {
            return 'Todos';
        }

        return match ($evento) {
            'inicio_sesion' => 'Inicio de sesión',
            'cierre_sesion' => 'Cierre de sesión',
            'contrasena_actualizada' => 'Contraseña actualizada',
            'contrasena_restablecida' => 'Contraseña restablecida',
            'email_verificado' => 'Email verificado',

            default => ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $evento
                )
            ),
        };
    };

    $formatearRegistro = static function (
        ?string $tipo,
        int|string|null $id
    ): string {
        if ($tipo === null || $id === null) {
            return 'No aplica';
        }

        $nombreTipo = match (class_basename($tipo)) {
            'User' =>
                'Usuario',

            'Paciente' =>
                'Paciente',

            'Kinesiologa' =>
                'Kinesióloga',

            'MatriculaProfesionalKinesiologia' =>
                'Matrícula profesional',

            'Jurisdiccion' =>
                'Jurisdicción',

            'TituloProfesional' =>
                'Título profesional',

            'ColegioEmisor' =>
                'Colegio emisor',

            'EspecialidadKinesiologia' =>
                'Especialidad de kinesiología',

            default =>
                class_basename($tipo),
        };

        return $nombreTipo . ' #' . $id;
    };

    $nombreExportador = trim(
        $exportador->name . ' ' . ($exportador->apellido ?? '')
    );
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta
        http-equiv="Content-Type"
        content="text/html; charset=utf-8"
    >

    <title>Reporte de Auditoría - KinesioSoft</title>

    <style>
        @page {
            margin: 28px 28px 42px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #1f2937;
            background: #ffffff;
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #1d4ed8;
            margin: 0;
        }

        .title {
            margin: 5px 0 0;
            font-size: 15px;
            font-weight: bold;
            color: #111827;
        }

        .subtitle {
            margin: 4px 0 0;
            font-size: 9px;
            color: #6b7280;
        }

        .info-table,
        .filters-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .info-table td,
        .filters-table td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            vertical-align: top;
        }

        .info-label {
            display: block;
            margin-bottom: 3px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
        }

        .info-value {
            font-size: 9px;
            font-weight: bold;
            color: #111827;
        }

        .section-title {
            margin: 0 0 7px;
            font-size: 10px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
        }

        .counter {
            margin: 0 0 8px;
            font-size: 8px;
            color: #4b5563;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .audit-table thead {
            display: table-header-group;
        }

        .audit-table tr {
            page-break-inside: avoid;
        }

        .audit-table th {
            padding: 6px 5px;
            border: 1px solid #cbd5e1;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 7px;
            text-align: left;
            text-transform: uppercase;
        }

        .audit-table td {
            padding: 6px 5px;
            border: 1px solid #dbe1e8;
            vertical-align: top;
            font-size: 7.5px;
            word-wrap: break-word;
        }

        .audit-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .col-id {
            width: 4%;
        }

        .col-fecha {
            width: 12%;
        }

        .col-actor {
            width: 22%;
        }

        .col-evento {
            width: 16%;
        }

        .col-modulo {
            width: 14%;
        }

        .col-registro {
            width: 18%;
        }

        .col-ip {
            width: 14%;
        }

        .muted {
            color: #6b7280;
        }

        .footer {
            position: fixed;
            bottom: -27px;
            left: 0;
            right: 0;
            border-top: 1px solid #d1d5db;
            padding-top: 6px;
            text-align: center;
            font-size: 7px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="header">
        <p class="brand">
            KinesioSoft
        </p>

        <p class="title">
            Reporte de Auditoría
        </p>

        <p class="subtitle">
            Documento PDF de respaldo generado por el sistema
        </p>
    </div>

    <table class="info-table">
        <tr>
            <td style="width: 25%;">
                <span class="info-label">
                    Fecha y hora de generación
                </span>

                <span class="info-value">
                    {{ $generadoEn->format('d/m/Y H:i:s') }}
                </span>
            </td>

            <td style="width: 30%;">
                <span class="info-label">
                    Exportado por
                </span>

                <span class="info-value">
                    {{ $nombreExportador !== '' ? $nombreExportador : '—' }}
                </span>
            </td>

            <td style="width: 30%;">
                <span class="info-label">
                    Correo
                </span>

                <span class="info-value">
                    {{ $exportador->email }}
                </span>
            </td>

            <td style="width: 15%;">
                <span class="info-label">
                    Rol
                </span>

                <span class="info-value">
                    {{ $rolExportador }}
                </span>
            </td>
        </tr>
    </table>

    <p class="section-title">
        Filtros aplicados
    </p>

    <table class="filters-table">
        <tr>
            <td style="width: 20%;">
                <span class="info-label">
                    Fecha desde
                </span>

                <span class="info-value">
                    {{ $formatearFechaFiltro($filtros['fecha_desde'] ?? null) }}
                </span>
            </td>

            <td style="width: 20%;">
                <span class="info-label">
                    Fecha hasta
                </span>

                <span class="info-value">
                    {{ $formatearFechaFiltro($filtros['fecha_hasta'] ?? null) }}
                </span>
            </td>

            <td style="width: 20%;">
                <span class="info-label">
                    Evento
                </span>

                <span class="info-value">
                    {{ $formatearEvento($filtros['evento'] ?? null) }}
                </span>
            </td>

            <td style="width: 20%;">
                <span class="info-label">
                    Módulo
                </span>

                <span class="info-value">
                    {{ $filtros['modulo'] ?? 'Todos' }}
                </span>
            </td>

            <td style="width: 20%;">
                <span class="info-label">
                    Ejecutado por
                </span>

                <span class="info-value">
                    {{ $filtros['actor'] ?? 'Todos' }}
                </span>
            </td>
        </tr>
    </table>

    <p class="section-title">
        Registros de auditoría
    </p>

    <p class="counter">
        Registros exportados:
        <strong>{{ $auditorias->count() }}</strong>
    </p>

    <table class="audit-table">
        <thead>
            <tr>
                <th class="col-id">
                    ID
                </th>

                <th class="col-fecha">
                    Fecha / Hora
                </th>

                <th class="col-actor">
                    Ejecutado por
                </th>

                <th class="col-evento">
                    Evento
                </th>

                <th class="col-modulo">
                    Módulo
                </th>

                <th class="col-registro">
                    Registro afectado
                </th>

                <th class="col-ip">
                    IP de origen
                </th>
            </tr>
        </thead>

        <tbody>
            @foreach ($auditorias as $auditoria)
                <tr>
                    <td>
                        #{{ $auditoria->id }}
                    </td>

                    <td>
                        {{ $auditoria->created_at?->format('d/m/Y H:i:s') ?? '—' }}
                    </td>

                    <td>
                        <strong>
                            {{ $auditoria->actor_nombre_snapshot ?? 'Sistema' }}
                        </strong>

                        @if ($auditoria->actor_email_snapshot)
                            <br>

                            <span class="muted">
                                {{ $auditoria->actor_email_snapshot }}
                            </span>
                        @endif
                    </td>

                    <td>
                        {{ $formatearEvento($auditoria->evento) }}
                    </td>

                    <td>
                        {{ $auditoria->modulo }}
                    </td>

                    <td>
                        {{ $formatearRegistro(
                            $auditoria->auditable_type,
                            $auditoria->auditable_id
                        ) }}
                    </td>

                    <td>
                        {{ $auditoria->ip_address ?? 'No disponible' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        KinesioSoft · Sistema de Gestión de Consultorio de Kinesiología ·
        Documento PDF de respaldo generado por el sistema
    </div>
</body>
</html>
