<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold leading-tight text-gray-900">Mis turnos</h1>
        <p class="mt-1 text-sm text-gray-500">Consultá tus próximos turnos y tu historial.</p>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <p id="estado-solicitud" class="text-sm text-gray-700">
                    @if ($primeraConsultaActiva)
                        Ya tenés una Primera consulta futura pendiente o confirmada. No podés solicitar otra por el momento.
                    @else
                        Podés iniciar una nueva solicitud de Primera consulta.
                    @endif
                </p>
                @if ($primeraConsultaActiva)
                    <button type="button" disabled aria-describedby="estado-solicitud" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600">Solicitar nuevo turno</button>
                @else
                    <a href="{{ route('turnos.solicitar.create') }}" aria-describedby="estado-solicitud" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Solicitar nuevo turno</a>
                @endif
            </div>

            @if ($proximos->total() === 0 && $historicos->total() === 0)
                <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Todavía no tenés turnos registrados</h2>
                    <p class="mt-2 text-sm text-gray-500">Tus próximos turnos y tu historial aparecerán aquí.</p>
                </div>
            @else
                @include('turnos.mis-turnos._listado', ['turnos' => $proximos, 'seccion' => 'proximos', 'titulo' => 'Próximos turnos', 'vacio' => 'No tenés próximos turnos.'])
                @include('turnos.mis-turnos._listado', ['turnos' => $historicos, 'seccion' => 'historicos', 'titulo' => 'Historial de turnos', 'vacio' => 'Todavía no tenés turnos en el historial.'])
            @endif
        </div>
    </div>
</x-app-layout>
