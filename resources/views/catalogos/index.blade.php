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
                        <p class="text-sm font-semibold text-indigo-900">
                            Módulo administrativo
                        </p>
                        <p class="text-sm text-indigo-800">
                            Desde aquí podrá administrar los catálogos generales y, más adelante, otros catálogos del dominio clínico.
                        </p>
                    </div>
                </div>
            </div>

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                <div class="border-b bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Catálogos disponibles</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Seleccione un catálogo para administrarlo.
                    </p>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">

                    <!-- Jurisdicciones -->
                    <a href="{{ route('catalogos.jurisdicciones.index') }}"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                        <h4 class="text-base font-semibold text-gray-800">Jurisdicciones</h4>
                        <p class="mt-2 text-sm text-gray-500">
                            Provincias o jurisdicciones emisoras de matrícula.
                        </p>
                    </a>

                    <!-- Colegios emisores -->
                    <a href="{{ route('catalogos.colegios.index') }}"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                        <h4 class="text-base font-semibold text-gray-800">Colegios emisores</h4>
                        <p class="mt-2 text-sm text-gray-500">
                            Entidades responsables de emitir matrículas profesionales.
                        </p>
                    </a>

                    <!-- Títulos profesionales -->
                    <a href="{{ route('catalogos.titulos.index') }}"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                        <h4 class="text-base font-semibold text-gray-800">Títulos profesionales</h4>
                        <p class="mt-2 text-sm text-gray-500">
                            Títulos habilitantes para el ejercicio profesional.
                        </p>
                    </a>

                    <!-- Especialidades de kinesiología -->
                    <a href="{{ route('catalogos.especialidades.index') }}"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                        <h4 class="text-base font-semibold text-gray-800">Especialidades de kinesiología</h4>
                        <p class="mt-2 text-sm text-gray-500">
                            Áreas profesionales disponibles para asociar al perfil de cada kinesióloga.
                        </p>
                    </a>

                </div>
            </section>
        </div>
    </div>

    <!-- SweetAlert "Próximamente" -->
    <script>
        function mostrarProximamente(e, modulo = '') {
            e.preventDefault();

            Swal.fire({
                icon: 'info',
                title: 'Próximamente',
                text: `El módulo "${modulo}" estará disponible en breve.`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#6366f1'
            });
        }
    </script>
</x-app-layout>
