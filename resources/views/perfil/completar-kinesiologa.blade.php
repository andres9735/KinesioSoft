<x-app-layout>
    @php
        $user = auth()->user();
        $kinesiologa = $user->kinesiologa;

        $matriculas = $kinesiologa
            ? $kinesiologa->matriculasProfesionales()->where('activa', true)->with(['jurisdiccion', 'colegioEmisor'])->get()
            : collect();

        $tieneMatriculas = $matriculas->isNotEmpty();

        $esEdicion =
            $kinesiologa &&
            !empty($kinesiologa->titulo_profesional_id) &&
            $matriculas->isNotEmpty() &&
            $kinesiologa->especialidades()->exists();

        $especialidadesSeleccionadas = old(
            'especialidades',
            $kinesiologa ? $kinesiologa->especialidades->pluck('id')->toArray() : []
        );
    @endphp

    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                {{ $esEdicion ? 'Editar perfil profesional' : 'Completar perfil profesional' }}
            </h2>
            <p class="text-sm text-gray-500">
                {{ $esEdicion
                    ? 'Actualice sus datos personales y profesionales.'
                    : 'Complete sus datos personales y profesionales para poder continuar usando el sistema.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form method="POST"
                  action="{{ route('perfil.kinesiologa.actualizar') }}"
                  class="space-y-6">
                @csrf

                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                    <div class="border-b bg-gray-50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 2a4 4 0 100 8 4 4 0 000-8z" />
                                    <path fill-rule="evenodd" d="M.458 16.042A8 8 0 0110 10a8 8 0 019.542 6.042A1 1 0 0118.57 17H1.43a1 1 0 01-.972-1.958z" clip-rule="evenodd" />
                                </svg>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Datos personales</h3>
                                <p class="text-sm text-gray-500">
                                    Información básica del profesional dentro del sistema.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="dni" :value="__('DNI')" />
                            <x-text-input
                                id="dni"
                                class="block mt-1 w-full"
                                type="text"
                                name="dni"
                                :value="old('dni', $user->dni)"
                                required
                            />
                            <p class="mt-1 text-sm text-gray-500">Ingrese el DNI sin puntos.</p>
                            <x-input-error :messages="$errors->get('dni')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="telefono" :value="__('Teléfono')" />
                            <x-text-input
                                id="telefono"
                                class="block mt-1 w-full"
                                type="text"
                                name="telefono"
                                :value="old('telefono', $user->telefono)"
                                required
                            />
                            <p class="mt-1 text-sm text-gray-500">Puede ingresar números, espacios, guiones o paréntesis.</p>
                            <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="fecha_nacimiento" :value="__('Fecha de nacimiento')" />
                            <x-text-input
                                id="fecha_nacimiento"
                                class="block mt-1 w-full"
                                type="date"
                                name="fecha_nacimiento"
                                :value="old('fecha_nacimiento', $user->fecha_nacimiento ? \Carbon\Carbon::parse($user->fecha_nacimiento)->format('Y-m-d') : '')"
                                required
                            />
                            <x-input-error :messages="$errors->get('fecha_nacimiento')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sexo" :value="__('Sexo')" />
                            <select
                                id="sexo"
                                name="sexo"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Seleccione una opción</option>
                                <option value="masculino" {{ old('sexo', $user->sexo) === 'masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="femenino" {{ old('sexo', $user->sexo) === 'femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="otro" {{ old('sexo', $user->sexo) === 'otro' ? 'selected' : '' }}>Otro</option>
                                <option value="prefiero_no_decir" {{ old('sexo', $user->sexo) === 'prefiero_no_decir' ? 'selected' : '' }}>Prefiero no decir</option>
                            </select>
                            <x-input-error :messages="$errors->get('sexo')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
                    <div class="border-b bg-gray-50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M4 3a1 1 0 000 2h1v10a2 2 0 002 2h6a2 2 0 002-2V5h1a1 1 0 100-2H4z" />
                                    <path d="M8 7a1 1 0 012 0v6a1 1 0 11-2 0V7zM12 7a1 1 0 112 0v6a1 1 0 11-2 0V7z" />
                                </svg>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Datos profesionales</h3>
                                <p class="text-sm text-gray-500">
                                    Título profesional, especialidades y estado general del perfil.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if ($tieneMatriculas)
                            <div class="md:col-span-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-semibold text-blue-900">
                                            Matrículas profesionales
                                        </h4>
                                        <p class="text-sm text-blue-800">
                                            Sus matrículas se administran desde el módulo específico. Desde esta pantalla puede actualizar
                                            sus datos personales, título profesional y especialidades.
                                        </p>
                                    </div>

                                    <a href="{{ route('perfil.kinesiologa.matriculas.index') }}"
                                       class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700">
                                        Mis matrículas profesionales
                                    </a>
                                </div>
                            </div>

                            <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-gray-50 p-5">
                                <h4 class="text-sm font-semibold text-gray-800">
                                    Matrículas activas registradas
                                </h4>

                                <div class="mt-4 grid grid-cols-1 gap-3">
                                    @foreach ($matriculas as $matricula)
                                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="space-y-1">
                                                    <p class="text-sm font-semibold text-gray-900">
                                                        {{ $matricula->jurisdiccion?->nombre ?? 'Jurisdicción' }}
                                                    </p>
                                                    <p class="text-sm text-gray-600">
                                                        {{ $matricula->colegioEmisor?->nombre ?? 'Colegio emisor' }}
                                                    </p>
                                                </div>

                                                <div class="text-sm font-medium text-gray-800">
                                                    {{ $matricula->nro_matricula_profesional }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4">
                                <h4 class="text-sm font-semibold text-amber-900">
                                    Primera matrícula profesional
                                </h4>
                                <p class="mt-1 text-sm text-amber-800">
                                    Para habilitar su perfil profesional, complete su primera matrícula. Luego podrá administrar
                                    matrículas adicionales desde el módulo <span class="font-semibold">Mis matrículas profesionales</span>.
                                </p>
                            </div>
                        @endif

                        <div class="md:col-span-2">
                            <x-input-label for="titulo_profesional_id" :value="__('Título profesional')" />
                            <select
                                id="titulo_profesional_id"
                                name="titulo_profesional_id"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Seleccione un título</option>
                                @foreach ($titulosProfesionales as $titulo)
                                    <option
                                        value="{{ $titulo->id }}"
                                        {{ old('titulo_profesional_id', $kinesiologa?->titulo_profesional_id) == $titulo->id ? 'selected' : '' }}
                                    >
                                        {{ $titulo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('titulo_profesional_id')" class="mt-2" />
                        </div>

                        @unless ($tieneMatriculas)
                            <div>
                                <x-input-label for="jurisdiccion_id" :value="__('Jurisdicción emisora')" />
                                <select
                                    id="jurisdiccion_id"
                                    name="jurisdiccion_id"
                                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Seleccione una jurisdicción</option>
                                    @foreach ($jurisdicciones as $jurisdiccion)
                                        <option
                                            value="{{ $jurisdiccion->id }}"
                                            {{ old('jurisdiccion_id') == $jurisdiccion->id ? 'selected' : '' }}
                                        >
                                            {{ $jurisdiccion->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('jurisdiccion_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="colegio_emisor_id" :value="__('Colegio emisor')" />
                                <select
                                    id="colegio_emisor_id"
                                    name="colegio_emisor_id"
                                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Primero seleccione una jurisdicción</option>
                                    @foreach ($colegiosEmisores as $colegio)
                                        <option
                                            value="{{ $colegio->id }}"
                                            data-jurisdiccion="{{ $colegio->jurisdiccion_id }}"
                                            {{ old('colegio_emisor_id') == $colegio->id ? 'selected' : '' }}
                                        >
                                            {{ $colegio->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-gray-500">
                                    Solo se mostrarán colegios correspondientes a la jurisdicción elegida.
                                </p>
                                <x-input-error :messages="$errors->get('colegio_emisor_id')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="nro_matricula_profesional" :value="__('Número de matrícula profesional')" />
                                <x-text-input
                                    id="nro_matricula_profesional"
                                    class="block mt-1 w-full"
                                    type="text"
                                    name="nro_matricula_profesional"
                                    :value="old('nro_matricula_profesional')"
                                    required
                                />
                                <p class="mt-1 text-sm text-gray-500">
                                    Ingrese el número de matrícula tal como figura en su credencial profesional.
                                </p>
                                <x-input-error :messages="$errors->get('nro_matricula_profesional')" class="mt-2" />
                            </div>
                        @endunless

                        <div class="md:col-span-2">
                            <x-input-label for="especialidades" :value="__('Especialidades')" />
                            <select
                                id="especialidades"
                                name="especialidades[]"
                                multiple
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                @foreach ($especialidades as $especialidad)
                                    <option
                                        value="{{ $especialidad->id }}"
                                        {{ in_array($especialidad->id, $especialidadesSeleccionadas) ? 'selected' : '' }}
                                    >
                                        {{ $especialidad->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Puede buscar y seleccionar una o varias especialidades.
                            </p>
                            <x-input-error :messages="$errors->get('especialidades')" class="mt-2" />
                            <x-input-error :messages="$errors->get('especialidades.*')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between rounded-2xl bg-white px-6 py-4 shadow-sm ring-1 ring-gray-100">
                    <p class="text-sm text-gray-500">
                        {{ $esEdicion ? 'Revise los datos antes de guardar los cambios.' : 'Una vez guardado, podrá ingresar normalmente al sistema.' }}
                    </p>

                    <x-primary-button>
                        {{ $esEdicion ? 'Guardar cambios' : 'Guardar y continuar' }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .ts-dropdown-especialidades {
            max-height: 320px;
            overflow-y: auto;
        }

        .ts-dropdown-especialidades .option,
        .ts-dropdown-especialidades .no-results {
            padding: 10px 12px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tieneMatriculas = @json($tieneMatriculas);

            const tituloSelect = document.getElementById('titulo_profesional_id');
            const jurisdiccionSelect = document.getElementById('jurisdiccion_id');
            const colegioSelect = document.getElementById('colegio_emisor_id');
            const especialidadesSelect = document.getElementById('especialidades');

            const colegiosOriginales = colegioSelect
                ? Array.from(colegioSelect.options)
                    .filter(option => option.value !== '')
                    .map(option => ({
                        value: option.value,
                        text: option.text,
                        jurisdiccion: option.dataset.jurisdiccion,
                    }))
                : [];

            let tituloTom = null;
            let jurisdiccionTom = null;
            let colegioTom = null;
            let especialidadesTom = null;

            if (window.TomSelect && tituloSelect) {
                tituloTom = new TomSelect('#titulo_profesional_id', {
                    create: false,
                    maxItems: 1,
                    allowEmptyOption: true,
                    placeholder: 'Seleccione un título'
                });
            }

            if (!tieneMatriculas && window.TomSelect && jurisdiccionSelect) {
                jurisdiccionTom = new TomSelect('#jurisdiccion_id', {
                    create: false,
                    maxItems: 1,
                    allowEmptyOption: true,
                    placeholder: 'Seleccione una jurisdicción'
                });
            }

            if (!tieneMatriculas && window.TomSelect && colegioSelect) {
                colegioTom = new TomSelect('#colegio_emisor_id', {
                    create: false,
                    maxItems: 1,
                    allowEmptyOption: true,
                    placeholder: 'Primero seleccione una jurisdicción'
                });
            }

            if (window.TomSelect && especialidadesSelect) {
                especialidadesTom = new TomSelect('#especialidades', {
                    plugins: ['remove_button'],
                    create: false,
                    maxItems: null,
                    hideSelected: true,
                    closeAfterSelect: false,
                    openOnFocus: true,
                    placeholder: 'Seleccione una o más especialidades',
                    dropdownClass: 'ts-dropdown-especialidades',
                    render: {
                        no_results: function(data, escape) {
                            return '<div class="no-results">No se encontraron resultados para "' + escape(data.input) + '"</div>';
                        }
                    }
                });
            }

            function actualizarColegiosPorJurisdiccion() {
                if (tieneMatriculas || !colegioSelect || !jurisdiccionSelect) {
                    return;
                }

                const jurisdiccionSeleccionada = jurisdiccionTom
                    ? jurisdiccionTom.getValue()
                    : jurisdiccionSelect.value;

                if (!colegioTom) {
                    colegioSelect.disabled = !jurisdiccionSeleccionada;

                    Array.from(colegioSelect.options).forEach(option => {
                        if (option.value === '') {
                            option.hidden = false;
                            option.text = jurisdiccionSeleccionada
                                ? 'Seleccione un colegio emisor'
                                : 'Primero seleccione una jurisdicción';
                            return;
                        }

                        option.hidden = option.dataset.jurisdiccion !== jurisdiccionSeleccionada;
                    });

                    const opcionActual = colegioSelect.options[colegioSelect.selectedIndex];
                    if (opcionActual && opcionActual.value !== '' && opcionActual.hidden) {
                        colegioSelect.value = '';
                    }

                    if (!jurisdiccionSeleccionada) {
                        colegioSelect.value = '';
                    }

                    return;
                }

                const valorActual = colegioTom.getValue();

                colegioTom.clear(true);
                colegioTom.clearOptions();

                colegioTom.addOption({
                    value: '',
                    text: jurisdiccionSeleccionada
                        ? 'Seleccione un colegio emisor'
                        : 'Primero seleccione una jurisdicción'
                });

                if (jurisdiccionSeleccionada) {
                    const colegiosFiltrados = colegiosOriginales
                        .filter(colegio => colegio.jurisdiccion === jurisdiccionSeleccionada)
                        .map(colegio => ({
                            value: colegio.value,
                            text: colegio.text
                        }));

                    colegioTom.addOptions(colegiosFiltrados);
                    colegioTom.enable();

                    const opcionValida = colegiosFiltrados.find(colegio => colegio.value == valorActual);
                    if (opcionValida) {
                        colegioTom.setValue(valorActual, true);
                    }
                } else {
                    colegioTom.clear(true);
                    colegioTom.disable();
                }

                colegioTom.refreshOptions(false);
            }

            if (!tieneMatriculas) {
                if (jurisdiccionTom) {
                    jurisdiccionTom.on('change', actualizarColegiosPorJurisdiccion);
                } else if (jurisdiccionSelect) {
                    jurisdiccionSelect.addEventListener('change', actualizarColegiosPorJurisdiccion);
                }

                actualizarColegiosPorJurisdiccion();
            }
        });
    </script>
</x-app-layout>
