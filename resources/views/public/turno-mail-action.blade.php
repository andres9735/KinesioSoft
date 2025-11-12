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
    .btn[disabled]{opacity:.5;cursor:not-allowed}
    dt{font-weight:600} dd{margin:0 0 .4rem}
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
      <dd>{{ $turno->estado }}</dd>
    </dl>

    @php
      $limConf = $turno->limiteConfirmacion();
      $limCanc = $turno->limiteCancelacion();
      $puedeConfirmar = $turno->puedeConfirmarAhora();
      $puedeCancelar  = $turno->puedeCancelarAhora();
    @endphp

    <p class="muted">
      @if ($limConf) Límite para confirmar: <strong>{{ $limConf->format('d/m H:i') }}</strong>.@endif
      @if ($limCanc) &nbsp;Límite para cancelar: <strong>{{ $limCanc->format('d/m H:i') }}</strong>.@endif
    </p>

    {{-- ✅ Usamos POST con URL firmada generada en el controlador --}}
    <form method="POST" action="{{ $postUrl }}" class="row">
      @csrf

      <button type="submit"
              class="btn btn-primary"
              name="accion"
              value="confirmar"
              @disabled(!$puedeConfirmar)>
        Confirmar asistencia
      </button>

      <button type="submit"
              class="btn btn-danger"
              name="accion"
              value="cancelar"
              @disabled(!$puedeCancelar)>
        Cancelar turno
      </button>
    </form>

    @if (!$puedeConfirmar || !$puedeCancelar)
      <p class="muted" style="margin-top:.75rem">
        @unless($puedeConfirmar) * No podés confirmar ahora (fuera de ventana permitida). @endunless
        @unless($puedeCancelar)  * No podés cancelar ahora (fuera de ventana permitida).  @endunless
      </p>
    @endif
  </div>
</body>
</html>

