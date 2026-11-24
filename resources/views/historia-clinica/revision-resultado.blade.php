<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Expediente clínico</h1></x-slot>
    @php
        // Modelos del resultado transaccional, incluido el replay. REVISADA es una
        // postcondición comprobada por ambos recorridos de la Action existente.
        $datosConfirmados = [
            'estado' => 'incorporada', 'historia' => $historia->nro_historia_clinica,
            'version' => (int) $version->numero_version, 'declaracion' => (int) $confirmacion->declaracion_salud_id,
            'turno' => (int) $turnoId, 'estadoDeclaracion' => 'Revisada', 'yaProcesada' => $yaProcesada,
            'autor' => $confirmacion->autor_nombre_snapshot, 'especialidad' => $confirmacion->especialidad_nombre_snapshot,
            'fecha' => $confirmacion->confirmada_at->format('d/m/Y H:i:s').' ('.config('app.timezone').')',
            'expedienteUrl' => route('historia-clinica.desde-turno', $turnoId),
            'turnoUrl' => route('turnos.agenda-atencion.show', $turnoId),
        ];
    @endphp
    {{-- La respuesta HTML existente se consume sobre DUX-06; no se añade una pantalla de éxito. --}}
    <section class="mx-auto max-w-4xl space-y-4 px-4 py-8" x-data="confirmacionRevision(@js([
        'respuestaInicial' => true, 'status' => 200, 'turno' => (int) $turnoId,
        'declaracion' => (int) $confirmacion->declaracion_salud_id,
        'expedienteUrl' => $datosConfirmados['expedienteUrl'], 'revisionUrl' => $datosConfirmados['expedienteUrl'],
        'turnoUrl' => $datosConfirmados['turnoUrl']]))">
        <div hidden data-resultado-revision="{{ json_encode($datosConfirmados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></div>
        <p role="status" x-text="aviso"></p>
        <noscript>
            <p>Revisión confirmada e incorporada</p>
            @if ($yaProcesada)<p>Esta confirmación ya fue procesada. El expediente puede contener versiones posteriores.</p>@endif
            <p>Consultá el expediente para ver la información vigente. El turno conserva su estado; esta operación no inicia la atención.</p>
        </noscript>
        <a data-recuperar-revision class="inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-4 py-2 font-semibold text-white" href="{{ $datosConfirmados['expedienteUrl'] }}">Consultar expediente actualizado</a>
    </section>
</x-app-layout>
