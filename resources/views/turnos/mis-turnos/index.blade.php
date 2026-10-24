<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold leading-tight text-gray-900">Mis turnos</h1>
                <p class="mt-1 text-sm text-gray-500">Consultá tus próximos turnos y el historial de atenciones.</p>
            </div>
            <a href="{{ route('turnos.solicitar.create') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-center text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M12 13v6m-3-3h6"/></svg>
                Solicitar nuevo turno
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($proximos->total() === 0 && $historicos->total() === 0)
                <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Todavía no tenés turnos registrados</h2>
                    <p class="mt-2 text-sm text-gray-500">Tus próximos turnos y tu historial aparecerán aquí.</p>
                </div>
            @else
                @include('turnos.mis-turnos._listado', ['turnos' => $proximos, 'seccion' => 'proximos', 'titulo' => 'Próximos turnos', 'vacio' => 'No tenés próximos turnos.'])
                @include('turnos.mis-turnos._listado', ['turnos' => $historicos, 'seccion' => 'historicos', 'titulo' => 'Historial de atenciones', 'vacio' => 'Todavía no tenés turnos en el historial.'])
            @endif
        </div>
    </div>
</x-app-layout>
