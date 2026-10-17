<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold leading-tight text-gray-900">Solicitar turno</h1>
        <p class="mt-1 text-sm text-gray-500">Completá los datos de tu Primera consulta.</p>
    </x-slot>

    <style>.solicitud-turno [x-cloak] { display: none !important; }</style>
    <div class="solicitud-turno py-8 sm:py-10" x-data="solicitarTurno(@js($configuracionWizard))">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <noscript><p class="rounded-lg bg-amber-50 p-4 text-amber-900">Necesitás habilitar JavaScript para completar esta solicitud. <a href="{{ route('turnos.mis-turnos.index') }}" class="underline">Volver a Mis turnos</a>.</p></noscript>
            <nav aria-label="Pasos de la solicitud" class="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                <ol class="hidden grid-cols-4 gap-4 md:grid">
                    @foreach (['Motivo y documentación', 'Elegir profesional', 'Fecha y horario', 'Revisar y confirmar'] as $indice => $nombre)
                        <li :aria-current="paso === {{ $indice + 1 }} ? 'step' : null" class="space-y-2 border-t-2 pt-3" :class="paso === {{ $indice + 1 }} ? 'border-indigo-600 text-indigo-700' : 'border-gray-200 text-gray-500'">
                            <span class="block text-xs">Paso {{ $indice + 1 }}</span>
                            <span class="block text-sm font-semibold">{{ $nombre }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="space-y-3 md:hidden" aria-live="polite">
                    <p class="text-sm font-semibold text-indigo-700" x-text="'Paso ' + paso + ' de 4'">Paso 1 de 4</p>
                    <p class="text-sm text-gray-700" x-text="pasos[paso - 1]">Motivo y documentación</p>
                    <progress class="h-2 w-full accent-indigo-600" max="4" value="1" :value="paso" aria-label="Progreso de la solicitud"></progress>
                </div>
            </nav>

            <p class="rounded-xl bg-indigo-50 px-5 py-4 text-sm text-indigo-900">Tipo de turno: <strong>Primera consulta</strong>. Los datos de esta página se enviarán al confirmar el turno.</p>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-8">
                @include('turnos.solicitar._motivo-documentacion')
                @include('turnos.solicitar._profesionales')
                @include('turnos.solicitar._fecha-horario')
                @include('turnos.solicitar._revision')
            </div>

            <div class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <button type="button" @click="volverAMisTurnos()" class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Volver a Mis turnos</button>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button" x-show="paso > 1" x-cloak @click="anterior()" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Anterior</button>
                    <button type="button" x-show="paso < 3" @click="siguiente()" :disabled="paso === 2 && profesionales.length === 0" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500">Continuar</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
