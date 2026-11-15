<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">Alertas de recordatorios</h2>
    </x-slot>
    <div class="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6">
        <p class="text-sm text-gray-700">Avisos internos por envíos que no pudieron completarse. Revisá el servicio de correo y la incidencia registrada en Auditoría. Estos avisos no cambian el estado de los turnos.</p>
        <p class="font-semibold text-gray-900">Alertas registradas: {{ $alertas->total() }}</p>
        @forelse ($alertas as $alerta)
            <article class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-gray-900">
                <h3 class="font-semibold">Turno #{{ (int) $alerta->turno_id }}</h3>
                <p class="text-sm text-gray-700">Recordatorio #{{ (int) $alerta->auditable_id }}</p>
                <p class="mt-1">{{ $mensajes[$alerta->valores_nuevos['incidencia']] }}</p>
                <p class="mt-2 text-sm text-gray-700">Registrado el {{ $alerta->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} (hora local).</p>
            </article>
        @empty
            <p class="rounded-lg border border-gray-200 bg-white p-4 text-gray-700">No hay alertas de envío registradas.</p>
        @endforelse
        {{ $alertas->links() }}
        <a href="{{ route('auditorias.index') }}" class="inline-flex rounded-md px-3 py-2 font-medium text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">Consultar Auditoría</a>
    </div>
</x-app-layout>
