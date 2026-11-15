<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-gray-900">Detalle administrativo del turno</h1>
    </x-slot>
    <section class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8" aria-labelledby="paciente-turno">
        <a href="{{ route('turnos.agenda-atencion.index', $contexto) }}" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Volver a la agenda</a>
        @include('turnos.agenda-atencion.detalle')
    </section>
</x-app-layout>
