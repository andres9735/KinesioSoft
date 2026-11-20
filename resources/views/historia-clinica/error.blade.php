<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Historia clínica</h1></x-slot>
    <section class="mx-auto max-w-4xl space-y-4 px-4 py-8" aria-label="Error de recuperación">
        <p role="alert">No se pudo recuperar el expediente. Su información queda pendiente de verificar. No se abrió una historia ni se modificó el turno o la declaración.</p>
        <a href="{{ route('historia-clinica.desde-turno', ['turno' => $turnoId]) }}" class="inline-flex min-h-11 items-center px-4 font-semibold text-indigo-700 underline">Reintentar</a>
        <a href="{{ route('turnos.agenda-atencion.index') }}" class="inline-flex min-h-11 items-center px-4 font-semibold text-indigo-700 underline">Volver a la agenda</a>
    </section>
</x-app-layout>
