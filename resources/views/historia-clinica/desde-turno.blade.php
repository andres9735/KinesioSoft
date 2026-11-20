<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-gray-900">Historia clínica del paciente</h1>
    </x-slot>
    <section class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8" aria-labelledby="paciente-expediente">
        <a href="{{ route('turnos.agenda-atencion.show', ['turno' => $turno->id]) }}" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Volver al turno</a>
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h2 id="paciente-expediente" class="text-xl font-semibold text-gray-900">{{ trim($paciente->user->name.' '.$paciente->user->apellido) }}</h2>
            <p class="mt-2 text-sm text-gray-700">Paciente identificado desde el turno registrado del {{ $turno->inicio_at->format('d/m/Y H:i') }}.</p>
            @if ($historia)
                <dl class="mt-4 space-y-2 text-sm text-gray-700">
                    <div><dt class="font-semibold">Número de historia clínica</dt><dd>{{ $historia->nro_historia_clinica }}</dd></div>
                    <div><dt class="font-semibold">Estado del expediente</dt><dd>{{ $historia->activa ? 'Activa' : 'Inactiva' }}</dd></div>
                </dl>
            @endif
        </div>

        @if ($declaracionPendiente)
            <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">
                <h2 class="font-semibold">Declaración de salud pendiente de revisión</h2>
                <p class="mt-2">La información declarada todavía no fue incorporada como datos clínicos profesionales. Esta declaración requiere revisión antes de incorporar sus datos al expediente.</p>
            </div>
        @endif

        @if (session('aviso_apertura'))
            <p role="status" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">{{ session('aviso_apertura') }}</p>
        @endif
        @if ($versionBase)
            @include('historia-clinica.version-base', ['version' => $versionBase])
        @endif

        <div class="space-y-3 rounded-2xl bg-white p-6 text-gray-800 shadow-sm">
            <h2 class="text-lg font-semibold">Recorrido siguiente</h2>
            @switch($recorrido)
                @case('apertura_a')
                    <p>Sin historia clínica ni declaración previa. Corresponde registrar los datos mínimos de apertura mediante la carga profesional.</p>
                    <p>La historia se creará activa al confirmar los datos mínimos de apertura.</p>
                    @can('abrirHistoriaClinica', [\App\Models\Turno::class, (int) $turno->id])
                        <a href="{{ route('historia-clinica.apertura.create', $turno->id) }}" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-4 py-2 font-semibold text-white">Registrar apertura profesional</a>
                    @endcan
                    @break
                @case('revision_c')
                    <p>Sin historia clínica y con declaración pendiente. Corresponde revisar la declaración de salud e incorporar los datos admitidos por la profesional.</p>
                    <p>La revisión confirmada y los datos mínimos permitirán abrir una única historia clínica activa.</p>
                    @break
                @case('historia_inactiva')
                    <p role="alert">La historia clínica está inactiva. Se detiene la apertura; corresponde gestionar su reactivación por el procedimiento aplicable.</p>
                    @break
                @case('declaracion_revisada_sin_historia')
                    <p role="alert">Hay una declaración revisada sin historia clínica. Se detiene la apertura hasta resolver esta inconsistencia por el procedimiento aplicable.</p>
                    @break
                @case('expediente_disponible')
                    <p>Se reutiliza el expediente activo existente. La historia está disponible; las demás condiciones para iniciar la atención deben verificarse antes de comenzar.</p>
                    @break
                @case('completar_mismo_expediente')
                    <p>Solo está disponible la cabecera. Corresponde completar los datos mínimos en el mismo expediente antes de iniciar la consulta.</p>
                    @break
                @default
                    <p>Se recuperó el expediente activo existente. La disponibilidad de sus datos de base está pendiente de verificar.</p>
                    <p>Si la profesional puede consultar el expediente de base histórico, se reutiliza esta historia al cumplir las demás condiciones para iniciar la atención. Si solo está disponible la cabecera, debe completar los datos mínimos en el mismo expediente antes de iniciar la consulta.</p>
                    <p>La cabecera y una declaración, aun revisada, no acreditan por sí solas que esos datos estén disponibles.</p>
            @endswitch
            @if ($historia?->activa && $declaracionPendiente)
                <p>Corresponde revisar la declaración pendiente. No impide por sí sola continuar hacia la consulta si los datos de base están disponibles y se cumplen las demás condiciones para iniciar la atención.</p>
            @endif
            <p class="text-sm text-gray-600">Esta pantalla es de consulta: no abre historias, no incorpora datos ni inicia la atención. No modifica el turno ni la declaración.</p>
        </div>
    </section>
</x-app-layout>
