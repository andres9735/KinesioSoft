<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">{{ ($errorConsulta ?? false) ? 'No pudimos consultar la agenda' : 'No pudimos preparar la agenda' }}</h1></x-slot>
    <section class="mx-auto max-w-4xl space-y-4 px-4 py-8" aria-label="Error de exportación">
        <p role="alert">{{ ($errorConsulta ?? false) ? 'No se pudo cargar la información solicitada.' : 'No se pudo preparar la representación solicitada.' }} Podés reintentar; los turnos no se modificaron.</p>
        <a href="{{ $reintentar }}" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500">Reintentar</a>
        <a href="{{ $volver }}" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500">Volver a la agenda</a>
    </section>
</x-app-layout>
