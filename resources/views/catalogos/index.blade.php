<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Catálogos
            </h2>
            <p class="text-sm text-gray-500">
                Administre las tablas maestras del sistema desde un único módulo.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0 text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10A8 8 0 114.293 4.293 8 8 0 0118 10zm-8-4a1 1 0 00-1 1v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-indigo-900">Módulo administrativo</p>
                        <p class="text-sm text-indigo-800">
                            Consulte los catálogos profesionales y gestione diagnósticos clínicos, pruebas funcionales, técnicas terapéuticas, categorías clínicas, ejercicios domiciliarios y tipos de equipo terapéutico desde este módulo.
                        </p>
                    </div>
                </div>
            </div>

            @include('catalogos.partials.navegacion')
        </div>
    </div>
</x-app-layout>
