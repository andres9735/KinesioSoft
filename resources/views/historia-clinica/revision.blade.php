<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Revisar declaración de salud</h1></x-slot>
    <section class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <a class="font-semibold text-indigo-700 underline" href="{{ route('historia-clinica.desde-turno', $turno->id) }}">Volver al expediente sin incorporar</a>
        <div class="space-y-2 rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">{{ trim($paciente->user->name.' '.$paciente->user->apellido) }}</h2>
            <p>Turno del {{ $turno->inicio_at->format('d/m/Y H:i') }}. Declaración #{{ $declaracion->id }} del {{ $declaracion->created_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }}).</p>
            <p>{{ $historia ? 'Se reutilizará el mismo expediente '.$historia->nro_historia_clinica : 'La futura incorporación requerirá abrir una historia clínica activa' }}.</p>
            <p>Origen de la futura versión: <strong>Revisión de declaración de salud</strong>.</p>
            <p class="text-sm text-gray-600">Este formulario prepara una vista previa. Salir no cambia la declaración ni los datos clínicos. Solo la confirmación explícita desde la vista previa incorpora los datos.</p>
        </div>
        @if ($versionBase)
            <details class="rounded-2xl bg-white p-4 shadow-sm"><summary class="cursor-pointer font-semibold">Consultar versión clínica previa que se conservará</summary>@include('historia-clinica.version-base', ['version' => $versionBase])</details>
        @endif
        @if ($erroresRevision->any())
            <div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-900">
                <h2 class="font-semibold">Revisá el resultado profesional</h2>
                <ul class="list-inside list-disc">@foreach ($erroresRevision->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @include('historia-clinica.revision-formulario')
    </section>
</x-app-layout>
