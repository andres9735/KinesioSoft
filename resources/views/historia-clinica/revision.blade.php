<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-slate-900">Revisión de declaración de salud</h1></x-slot>
    <style>#revision-formulario [x-cloak] { display: none !important; }</style>
    <section class="mx-auto max-w-screen-2xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        <a class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-700 underline" href="{{ route('historia-clinica.desde-turno', $turno->id) }}"><span aria-hidden="true">←</span> Volver al expediente sin incorporar</a>
        @include('historia-clinica.revision-formulario', ['redisenioRevision' => true])
    </section>
</x-app-layout>
