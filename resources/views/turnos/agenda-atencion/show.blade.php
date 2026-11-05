<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-gray-900">Detalle administrativo del turno</h1>
    </x-slot>
    <section class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8" aria-labelledby="paciente-turno">
        <a href="{{ route('turnos.agenda-atencion.index', $contexto) }}" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Volver a la agenda</a>
        <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
            <h2 id="paciente-turno" class="break-words text-xl font-semibold text-gray-900">{{ $turno['paciente']['nombre'] ?? 'Paciente sin nombre registrado' }}</h2>
            <dl class="grid gap-4 text-gray-700 sm:grid-cols-2">
                <div><dt class="font-semibold">Tipo de consulta</dt><dd>{{ $turno['tipo']->value === 'PRIMERA_CONSULTA' ? 'Primera consulta' : 'Seguimiento' }}</dd></div>
                <div><dt class="font-semibold">Profesional</dt><dd class="break-words">{{ $turno['kinesiologa']['nombre'] ?? 'Profesional sin nombre registrado' }}</dd></div>
                <div><dt class="font-semibold">Inicio (hora local)</dt><dd><time datetime="{{ $turno['inicio_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['inicio_at']->format('d/m/Y H:i') }}</time></dd></div>
                <div><dt class="font-semibold">Fin (hora local)</dt><dd><time datetime="{{ $turno['fin_at']->format('Y-m-d\TH:i:s') }}">{{ $turno['fin_at']->format('d/m/Y H:i') }}</time></dd></div>
                <div><dt class="font-semibold">Duración</dt><dd>{{ $turno['inicio_at']->diffInMinutes($turno['fin_at']) }} minutos</dd></div>
                <div><dt class="font-semibold">Estado</dt><dd>{{ match ($turno['estado']->value) {
                    'PENDIENTE' => 'Pendiente', 'CONFIRMADO' => 'Confirmado', 'EN_ATENCION' => 'En atención',
                    'ATENDIDO' => 'Atendido', 'AUSENTE' => 'Ausente', 'CANCELADO' => 'Cancelado',
                } }}</dd></div>
            </dl>
        </div>
    </section>
</x-app-layout>
