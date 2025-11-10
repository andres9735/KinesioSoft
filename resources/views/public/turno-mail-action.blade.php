<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Confirmar / Cancelar turno</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,sans-serif;margin:2rem}.card{border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.25rem;max-width:640px}.ok{background:#ecfdf5;color:#065f46;border:1px solid #34d399;padding:.6rem .8rem;border-radius:10px;margin-bottom:1rem}.btn{padding:.6rem 1rem;border-radius:.6rem;border:0;cursor:pointer}.btn-primary{background:#2563eb;color:#fff}.btn-danger{background:#dc2626;color:#fff}</style>
</head>
<body>
  @if (session('status')) <p class="ok">{{ session('status') }}</p> @endif
  <h1>Confirmación de turno</h1>
  <div class="card">
    <p><strong>Paciente:</strong> {{ $turno->paciente->name }}</p>
    <p><strong>Profesional:</strong> {{ $turno->profesional->name }}</p>
    <p><strong>Fecha:</strong> {{ $turno->fecha?->format('d/m/Y') }}</p>
    <p><strong>Horario:</strong> {{ substr((string)$turno->hora_desde,0,5) }}–{{ substr((string)$turno->hora_hasta,0,5) }}</p>
    <p><strong>Estado actual:</strong> {{ $turno->estado }}</p>

    <form method="post" action="{{ route('turnos.mail-action.store', request()->query()) }}" style="margin-top:1rem">
      @csrf
      <input type="hidden" name="turno_id" value="{{ $turno->id_turno }}">
      <input type="hidden" name="accion" value="{{ $accion }}">
      <button type="submit" class="btn {{ $accion === 'confirmar' ? 'btn-primary' : 'btn-danger' }}">
        {{ $accion === 'confirmar' ? 'Confirmar asistencia' : 'Cancelar turno' }}
      </button>
    </form>
  </div>
</body>
</html>
