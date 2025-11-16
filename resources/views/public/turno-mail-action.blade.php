<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Confirmar / Cancelar turno</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{color-scheme:light dark}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:2rem}
    .card{border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.25rem;max-width:720px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #34d399;padding:.6rem .8rem;border-radius:10px;margin-bottom:1rem}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;padding:.6rem .8rem;border-radius:10px;margin-bottom:1rem}
    .muted{color:#6b7280;font-size:.95rem}
    .row{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem}
    .btn{padding:.6rem 1rem;border-radius:.6rem;border:0;cursor:pointer}
    .btn-primary{background:#2563eb;color:#fff}
    .btn-danger{background:#dc2626;color:#fff}
    .btn-outline{background:transparent;border:1px solid currentColor}
    .btn[disabled]{opacity:.5;cursor:not-allowed}
    dt{font-weight:600} dd{margin:0 0 .4rem}
    .badge{display:inline-block;border-radius:999px;padding:.15rem .6rem;font-size:.85rem;border:1px solid #e5e7eb}
    .badge-warning{background:#fff7ed;color:#9a3412;border-color:#fed7aa}
    .badge-success{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
    .badge-danger{background:#fef2f2;color:#991b1b;border-color:#fecaca}
    .badge-gray{background:#f3f4f6;color:#374151;border-color:#e5e7eb}
  </style>
</head>
<body>
  @if (session('status'))
    <p class="ok">{{ session('status') }}</p>
  @endif

  @if ($errors->any())
    <div class="err">
      <ul style="margin:0;padding-left:1.2rem">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <h1>Confirmación de turno</h1>

  @php
    // Datos y helpers
    $estado          = $turno->estado;
    $yaCancelado     = in_array($estado, ['cancelado','cancelado_tarde'], true);
    $yaConfirmado    = $estado === 'confirmado';
    $turnoPasado     = $turno->fin && now()->gte($turno->fin);

    $limConf         = $turno->limiteConfirmacion();
    $limCanc         = $turno->limiteCancelacion();
    $puedeConfirmar  = !$yaCancelado && !$turnoPasado && $turno->puedeConfirmarAhora();
    $puedeCancelar   = !$yaCancelado && !$turnoPasado && $turno->puedeCancelarAhora();

    $accionPref      = $accion ?? null; // viene por query (?accion=confirmar|cancelar)
    $confirmClasses  = 'btn btn-primary';
    $cancelClasses   = 'btn btn-danger';

    // Si vino ?accion=cancelar, destacamos cancelar (y viceversa)
    if ($accionPref === 'cancelar') {
        $cancelClasses  = 'btn btn-danger';
        $confirmClasses = 'btn btn-outline';
    } elseif ($accionPref === 'confirmar') {
        $confirmClasses = 'btn btn-primary';
        $cancelClasses  = 'btn btn-outline';
    }

    // Etiquetas según estado actual
    $labelConfirm = $yaConfirmado ? 'Ya confirmado' : 'Confirmar asistencia';
    $labelCancel  = $yaCancelado  ? 'Ya cancelado'  : 'Cancelar turno';

    // Deshabilitar si corresponde
    $disableConfirm = $yaCancelado || $yaConfirmado || $turnoPasado || !$puedeConfirmar;
    $disableCancel  = $yaCancelado || $turnoPasado || !$puedeCancelar;

    // Badge de estado
    $badgeClass = match ($estado) {
        'pendiente'        => 'badge badge-warning',
        'confirmado'       => 'badge badge-success',
        'cancelado','cancelado_tarde' => 'badge badge-danger',
        default            => 'badge badge-gray',
    };

    $confirmMin = (int) config('turnos.confirm_min_minutes', 180);
    $cancelMin  = (int) config('turnos.cancel_min_minutes', 1440);
  @endphp

  <div class="card">
    <dl>
      <dt>Paciente</dt>
      <dd>{{ $turno->paciente->name }}</dd>

      <dt>Profesional</dt>
      <dd>{{ $turno->profesional->name }}</dd>

      <dt>Fecha</dt>
      <dd>{{ $turno->fecha?->format('d/m/Y') }}</dd>

      <dt>Horario</dt>
      <dd>{{ substr((string)$turno->hora_desde,0,5) }}–{{ substr((string)$turno->hora_hasta,0,5) }}</dd>

      <dt>Estado actual</dt>
      <dd><span class="{{ $badgeClass }}">{{ $estado }}</span></dd>
    </dl>

    @if ($turnoPasado)
      <p class="muted">El turno ya pasó. No es posible confirmarlo ni cancelarlo ahora.</p>
    @else
      <p class="muted">
        @if ($limConf) Límite para confirmar: <strong>{{ $limConf->format('d/m H:i') }}</strong>.@endif
        @if ($limCanc) &nbsp;Límite para cancelar: <strong>{{ $limCanc->format('d/m H:i') }}</strong>.@endif
      </p>

      {{-- ✅ Usamos POST con URL firmada generada en el controlador --}}
      <form method="POST" action="{{ $postUrl }}" class="row">
        @csrf

        <button type="submit"
                class="{{ $confirmClasses }}"
                name="accion"
                value="confirmar"
                @disabled($disableConfirm)>
          {{ $labelConfirm }}
        </button>

        <button type="submit"
                class="{{ $cancelClasses }}"
                name="accion"
                value="cancelar"
                @disabled($disableCancel)>
          {{ $labelCancel }}
        </button>
      </form>

      @if ($yaConfirmado || $yaCancelado || !$puedeConfirmar || !$puedeCancelar)
        <p class="muted" style="margin-top:.75rem">
          @if ($yaConfirmado)* Este turno ya está confirmado.@endif
          @if ($yaCancelado)* Este turno ya fue cancelado.@endif
          @unless($puedeConfirmar)
            * No podés confirmar ahora (se requiere al menos {{ floor($confirmMin/60) }} h de antelación).
          @endunless
          @unless($puedeCancelar)
            * No podés cancelar ahora (se requiere al menos {{ floor($cancelMin/60) }} h de antelación).
          @endunless
        </p>
      @endif
    @endif
  </div>
</body>
</html>



