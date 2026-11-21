<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Revisión de declaración</h1></x-slot>
    <section class="mx-auto max-w-4xl space-y-4 px-4 py-8">
        <p role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4">El formulario es inválido o cambió el contexto de revisión. Volvé al expediente y abrí un nuevo formulario. No se incorporaron datos.</p>
        <a class="font-semibold text-indigo-700 underline" href="{{ route('historia-clinica.desde-turno', $turnoId) }}">Volver al expediente</a>
    </section>
</x-app-layout>
