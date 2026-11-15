<div class="uc05-detalle-contenido rounded-2xl bg-white p-4 text-left sm:p-6">
    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Paciente</p>
    <h2 id="paciente-turno" class="mt-1 break-words text-xl font-semibold text-gray-900">{{ $turno['paciente']['nombre'] ?? 'Paciente sin nombre registrado' }}</h2>
    <dl class="uc05-detalle-datos mt-5 text-sm text-gray-700">
        <div><dt class="text-xs font-medium text-gray-600">Inicio (hora local)</dt><dd class="mt-1 font-semibold tabular-nums text-gray-900"><time datetime="{{ $turno['inicio_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['inicio_at']->format('d/m/Y H:i') }}</time></dd></div>
        <div><dt class="text-xs font-medium text-gray-600">Fin (hora local)</dt><dd class="mt-1 font-semibold tabular-nums text-gray-900"><time datetime="{{ $turno['fin_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['fin_at']->format('d/m/Y H:i') }}</time></dd></div>
        <div><dt class="text-xs font-medium text-gray-600">Duración</dt><dd class="mt-1 font-medium text-gray-900">{{ $turno['inicio_at']->diffInMinutes($turno['fin_at']) }} minutos</dd></div>
        <div><dt class="text-xs font-medium text-gray-600">Estado</dt><dd class="mt-1">@include('turnos.agenda-atencion.estado', ['estado' => $turno['estado']])</dd></div>
        <div><dt class="text-xs font-medium text-gray-500">Tipo de consulta</dt><dd class="mt-1 font-medium text-gray-900">{{ $turno['tipo']->value === 'PRIMERA_CONSULTA' ? 'Primera consulta' : 'Seguimiento' }}</dd></div>
        <div><dt class="text-xs font-medium text-gray-500">Profesional</dt><dd class="mt-1 break-words font-medium text-gray-900">{{ $turno['kinesiologa']['nombre'] ?? 'Profesional sin nombre registrado' }}</dd></div>
    </dl>
</div>
