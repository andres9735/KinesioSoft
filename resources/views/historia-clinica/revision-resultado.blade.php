<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Revisión de declaración incorporada</h1></x-slot>
    <section class="mx-auto max-w-4xl space-y-6 px-4 py-8">
        <div role="status" class="space-y-3 rounded-xl border border-green-300 bg-green-50 p-4">
            <h2 class="text-xl font-semibold">{{ $yaProcesada ? 'Esta confirmación ya fue procesada' : 'Revisión confirmada e incorporada' }}</h2>
            <p>{{ $yaProcesada ? 'Se recuperó la incorporación registrada. No se generaron otra versión, dictámenes ni auditorías.' : 'La declaración pasó a Revisada y sus datos admitidos quedaron registrados en una versión clínica profesional.' }}</p>
            <p>Expediente: {{ $historia->nro_historia_clinica }} · Versión registrada: {{ $version->numero_version }} · Declaración #{{ $confirmacion->declaracion_salud_id }}.</p>
            <p>Confirmación de {{ $confirmacion->autor_nombre_snapshot }} · {{ $confirmacion->especialidad_nombre_snapshot }} · {{ $confirmacion->confirmada_at->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }}).</p>
            <p>Origen: Revisión de declaración de salud. El turno conserva su estado; esta operación no inicia la atención.</p>
            @if ($yaProcesada)<p>El expediente puede contener versiones posteriores. Consultalo para ver la versión vigente.</p>@endif
        </div>
        <a class="inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-4 py-2 font-semibold text-white" href="{{ route('historia-clinica.desde-turno', $turnoId) }}">Consultar expediente actualizado</a>
    </section>
</x-app-layout>
