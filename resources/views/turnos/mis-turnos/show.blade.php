@php
    $tipo = $detalle['tipo'] === \App\Enums\TipoTurnoEnum::PRIMERA_CONSULTA ? 'Primera consulta' : 'Seguimiento';
    $estados = [
        'PENDIENTE' => 'Pendiente', 'CONFIRMADO' => 'Confirmado', 'EN_ATENCION' => 'En atención',
        'ATENDIDO' => 'Atendido', 'AUSENTE' => 'Ausente', 'CANCELADO' => 'Cancelado',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold leading-tight text-gray-900">Detalle del turno</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $tipo }} · {{ $estados[$detalle['estado']->value] }}</p>
            </div>
            <a href="{{ $urlVolver }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Volver a Mis turnos</a>
        </div>
    </x-slot>
    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8 [overflow-wrap:anywhere]">
            <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6" aria-labelledby="datos-turno-titulo">
                <h2 id="datos-turno-titulo" class="text-lg font-semibold text-gray-900">Información del turno</h2>
                <p class="mt-2 text-sm text-gray-500">{{ $detalle['seccion'] === 'proximos' ? 'Próximos' : 'Historial' }}</p>
                <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-gray-500">Tipo de turno</dt><dd class="mt-1 text-gray-900">{{ $tipo }}</dd></div>
                    <div><dt class="font-medium text-gray-500">Estado</dt><dd class="mt-1 text-gray-900">{{ $estados[$detalle['estado']->value] }}</dd></div>
                    <div><dt class="font-medium text-gray-500">Profesional</dt><dd class="mt-1 text-gray-900">{{ $detalle['profesional'] ?: 'Profesional no disponible' }}</dd></div>
                    <div><dt class="font-medium text-gray-500">Fecha</dt><dd class="mt-1 text-gray-900"><time datetime="{{ $detalle['inicio_at']->format('Y-m-d') }}">{{ $detalle['inicio_at']->format('d/m/Y') }}</time></dd></div>
                    <div><dt class="font-medium text-gray-500">Horario</dt><dd class="mt-1 text-gray-900">{{ $detalle['inicio_at']->format('H:i') }} – {{ $detalle['fin_at']->format('H:i') }} hs</dd></div>
                </dl>
            </section>
            @if ($detalle['tipo'] === \App\Enums\TipoTurnoEnum::PRIMERA_CONSULTA)
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6" aria-labelledby="solicitud-titulo">
                    <h2 id="solicitud-titulo" class="text-lg font-semibold text-gray-900">Datos de la solicitud</h2>
                    <h3 class="mt-4 font-medium text-gray-900">Motivo original</h3>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700">{{ filled($detalle['motivo']) ? $detalle['motivo'] : 'Este turno no tiene un motivo de solicitud registrado.' }}</p>
                    <h3 class="mt-6 font-medium text-gray-900">Documentos de la solicitud</h3>
                    @if ($detalle['documentos']->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Este turno no tiene documentos de solicitud disponibles.</p>
                    @else
                        <ul class="mt-3 space-y-4">
                            @foreach ($detalle['documentos'] as $documento)
                                <li class="min-w-0 rounded-lg border border-gray-100 p-4">
                                    <p class="font-medium text-gray-900">{{ $documento['nombre'] }}</p>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-gray-600">{{ filled($documento['descripcion']) ? $documento['descripcion'] : 'Sin descripción registrada.' }}</p>
                                    <a href="{{ $documento['url'] }}" class="mt-2 inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-indigo-700 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" aria-label="Descargar {{ $documento['nombre'] }}">Descargar documento</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @else
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6" aria-labelledby="seguimiento-titulo">
                    <h2 id="seguimiento-titulo" class="text-lg font-semibold text-gray-900">Información del seguimiento</h2>
                    <p class="mt-2 text-sm text-gray-500">No hay información de plan o sesión registrada para este turno.</p>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
