<x-app-layout>
    @php
        $user = auth()->user();
        $kinesiologa = $user->kinesiologa;

        $matriculas = $kinesiologa
            ? $kinesiologa
                ->matriculasProfesionales()
                ->where('activa', true)
                ->with(['jurisdiccion', 'colegioEmisor'])
                ->get()
            : collect();

        $tieneMatriculas = $matriculas->isNotEmpty();

        $esEdicion =
            $kinesiologa &&
            ! empty($kinesiologa->titulo_profesional_id) &&
            $matriculas->isNotEmpty() &&
            $kinesiologa->especialidades()->exists();

        $especialidadesSeleccionadas = old(
            'especialidades',
            $kinesiologa
                ? $kinesiologa->especialidades->pluck('id')->toArray()
                : []
        );

        $colegioSeleccionado = old('colegio_emisor_id');
    @endphp

    @unless ($esEdicion)
        <form
            id="logout-form"
            method="POST"
            action="{{ route('logout') }}"
            class="hidden"
        >
            @csrf
        </form>
    @endunless

    <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="mx-auto max-w-[820px]">
            <div class="overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/70">
                {{-- Encabezado --}}
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 px-6 py-7 text-white sm:px-8 sm:py-9">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-6 w-6"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                                {{ $esEdicion
                                    ? 'Editar perfil profesional'
                                    : 'Completar perfil profesional' }}
                            </h1>

                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                                {{ $esEdicion
                                    ? 'Actualice sus datos personales y profesionales.'
                                    : 'Complete sus datos personales y profesionales para configurar su cuenta y continuar usando KinesioSoft.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('perfil.kinesiologa.actualizar') }}"
                    class="perfil-profesional-form"
                >
                    @csrf

                    <div class="space-y-10 px-6 py-8 sm:px-8 sm:py-10">
                        {{-- Datos personales --}}
                        <section>
                            <div class="mb-6 flex items-center gap-3 border-b border-slate-200 pb-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-5 w-5"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-lg font-bold text-slate-900">
                                        Datos personales
                                    </h2>
                                    <p class="text-sm text-slate-500">
                                        Información básica de identificación y contacto.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                                {{-- DNI --}}
                                <div>
                                    <label
                                        for="dni"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        DNI
                                        <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>

                                    <input
                                        id="dni"
                                        name="dni"
                                        type="text"
                                        value="{{ old('dni', $user->dni) }}"
                                        placeholder="Ej: 30123456"
                                        required
                                        autocomplete="off"
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <p class="mt-1.5 text-xs leading-5 text-slate-500">
                                        Ingrese el DNI sin puntos.
                                    </p>

                                    <x-input-error
                                        :messages="$errors->get('dni')"
                                        class="mt-2"
                                    />
                                </div>

                                {{-- Fecha de nacimiento --}}
                                <div>
                                    <label
                                        for="fecha_nacimiento"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Fecha de nacimiento
                                        <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>

                                    <input
                                        id="fecha_nacimiento"
                                        name="fecha_nacimiento"
                                        type="date"
                                        value="{{ old(
                                            'fecha_nacimiento',
                                            $user->fecha_nacimiento
                                                ? \Carbon\Carbon::parse($user->fecha_nacimiento)->format('Y-m-d')
                                                : ''
                                        ) }}"
                                        required
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <x-input-error
                                        :messages="$errors->get('fecha_nacimiento')"
                                        class="mt-2"
                                    />
                                </div>

                                {{-- Sexo --}}
                                <div>
                                    <label
                                        for="sexo"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Sexo
                                        <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>

                                    <select
                                        id="sexo"
                                        name="sexo"
                                        required
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">
                                            Seleccione una opción
                                        </option>

                                        <option
                                            value="masculino"
                                            {{ old('sexo', $user->sexo) === 'masculino' ? 'selected' : '' }}
                                        >
                                            Masculino
                                        </option>

                                        <option
                                            value="femenino"
                                            {{ old('sexo', $user->sexo) === 'femenino' ? 'selected' : '' }}
                                        >
                                            Femenino
                                        </option>

                                        <option
                                            value="otro"
                                            {{ old('sexo', $user->sexo) === 'otro' ? 'selected' : '' }}
                                        >
                                            Otro
                                        </option>

                                        <option
                                            value="prefiero_no_decir"
                                            {{ old('sexo', $user->sexo) === 'prefiero_no_decir' ? 'selected' : '' }}
                                        >
                                            Prefiero no decir
                                        </option>
                                    </select>

                                    <x-input-error
                                        :messages="$errors->get('sexo')"
                                        class="mt-2"
                                    />
                                </div>

                                {{-- Teléfono --}}
                                <div>
                                    <label
                                        for="telefono"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Teléfono
                                        <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>

                                    <input
                                        id="telefono"
                                        name="telefono"
                                        type="text"
                                        value="{{ old('telefono', $user->telefono) }}"
                                        placeholder="Ej: 3764 123456"
                                        required
                                        autocomplete="tel"
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <p class="mt-1.5 text-xs leading-5 text-slate-500">
                                        Puede ingresar números, espacios, guiones o paréntesis.
                                    </p>

                                    <x-input-error
                                        :messages="$errors->get('telefono')"
                                        class="mt-2"
                                    />
                                </div>
                            </div>
                        </section>

                        {{-- Datos profesionales --}}
                        <section>
                            <div class="mb-6 flex items-center gap-3 border-b border-slate-200 pb-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-5 w-5"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4.5 6.75h15M6.75 6.75V18a1.5 1.5 0 001.5 1.5h7.5a1.5 1.5 0 001.5-1.5V6.75M9 6.75V4.5A1.5 1.5 0 0110.5 3h3A1.5 1.5 0 0115 4.5v2.25"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-lg font-bold text-slate-900">
                                        Datos profesionales
                                    </h2>
                                    <p class="text-sm text-slate-500">
                                        Título, matrícula profesional y especialidades.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                                {{-- Información cuando ya tiene matrículas --}}
                                @if ($tieneMatriculas)
                                    <div class="md:col-span-2 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="flex gap-3">
                                                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        class="h-5 w-5"
                                                        aria-hidden="true"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M12 9h.01M11 12h1v4h1m8-4a9 9 0 11-18 0 9 9 0 0118 0z"
                                                        />
                                                    </svg>
                                                </div>

                                                <div>
                                                    <h3 class="text-sm font-bold text-blue-950">
                                                        Matrículas profesionales
                                                    </h3>

                                                    <p class="mt-1 text-sm leading-6 text-blue-800">
                                                        Sus matrículas se administran desde el módulo específico.
                                                        Desde esta pantalla puede actualizar sus datos personales,
                                                        título profesional y especialidades.
                                                    </p>
                                                </div>
                                            </div>

                                            <a
                                                href="{{ route('perfil.kinesiologa.matriculas.index') }}"
                                                class="inline-flex shrink-0 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            >
                                                Mis matrículas profesionales
                                            </a>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                        <h3 class="text-sm font-bold text-slate-800">
                                            Matrículas activas registradas
                                        </h3>

                                        <div class="mt-4 space-y-3">
                                            @foreach ($matriculas as $matricula)
                                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-slate-900">
                                                                {{ $matricula->jurisdiccion?->nombre ?? 'Jurisdicción' }}
                                                            </p>

                                                            <p class="mt-0.5 text-sm text-slate-500">
                                                                {{ $matricula->colegioEmisor?->nombre ?? 'Colegio emisor' }}
                                                            </p>
                                                        </div>

                                                        <div class="shrink-0 rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-800">
                                                            {{ $matricula->nro_matricula_profesional }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="md:col-span-2 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                                        <div class="flex gap-3">
                                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    class="h-5 w-5"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M12 9h.01M11 12h1v4h1m8-4a9 9 0 11-18 0 9 9 0 0118 0z"
                                                    />
                                                </svg>
                                            </div>

                                            <div>
                                                <h3 class="text-sm font-bold text-blue-950">
                                                    Primera matrícula profesional
                                                </h3>

                                                <p class="mt-1 text-sm leading-6 text-blue-800">
                                                    Para habilitar su perfil profesional, complete su primera
                                                    matrícula. Luego podrá administrar matrículas adicionales
                                                    desde el módulo
                                                    <span class="font-semibold">Mis matrículas profesionales</span>.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Título profesional --}}
                                <div class="md:col-span-2">
                                    <label
                                        for="titulo_profesional_id"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Título profesional
                                        <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>

                                    <select
                                        id="titulo_profesional_id"
                                        name="titulo_profesional_id"
                                        data-tom-select="simple"
                                        data-tom-select-placeholder="Seleccione un título"
                                        required
                                        class="mt-2 block w-full rounded-xl border-slate-300"
                                    >
                                        <option value="">
                                            Seleccione un título
                                        </option>

                                        @foreach ($titulosProfesionales as $titulo)
                                            <option
                                                value="{{ $titulo->id }}"
                                                {{ old(
                                                    'titulo_profesional_id',
                                                    $kinesiologa?->titulo_profesional_id
                                                ) == $titulo->id ? 'selected' : '' }}
                                            >
                                                {{ $titulo->nombre }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-input-error
                                        :messages="$errors->get('titulo_profesional_id')"
                                        class="mt-2"
                                    />
                                </div>

                                @unless ($tieneMatriculas)
                                    {{-- Jurisdicción --}}
                                    <div>
                                        <label
                                            for="jurisdiccion_id"
                                            class="block text-sm font-semibold text-slate-700"
                                        >
                                            Jurisdicción emisora
                                            <span class="text-rose-500" aria-hidden="true">*</span>
                                        </label>

                                        <select
                                            id="jurisdiccion_id"
                                            name="jurisdiccion_id"
                                            data-tom-select="simple"
                                            data-tom-select-placeholder="Seleccione una jurisdicción"
                                            data-jurisdiccion-colegio-target="#colegio_emisor_id"
                                            required
                                            class="mt-2 block w-full rounded-xl border-slate-300"
                                        >
                                            <option value="">
                                                Seleccione una jurisdicción
                                            </option>

                                            @foreach ($jurisdicciones as $jurisdiccion)
                                                <option
                                                    value="{{ $jurisdiccion->id }}"
                                                    {{ old('jurisdiccion_id') == $jurisdiccion->id ? 'selected' : '' }}
                                                >
                                                    {{ $jurisdiccion->nombre }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <x-input-error
                                            :messages="$errors->get('jurisdiccion_id')"
                                            class="mt-2"
                                        />
                                    </div>

                                    {{-- Colegio emisor --}}
                                    <div>
                                        <label
                                            for="colegio_emisor_id"
                                            class="block text-sm font-semibold text-slate-700"
                                        >
                                            Colegio emisor
                                            <span class="text-rose-500" aria-hidden="true">*</span>
                                        </label>

                                        <select
                                            id="colegio_emisor_id"
                                            name="colegio_emisor_id"
                                            data-tom-select="simple"
                                            data-tom-select-placeholder="Primero seleccione una jurisdicción"
                                            data-colegio-placeholder-sin-jurisdiccion="Primero seleccione una jurisdicción"
                                            data-colegio-placeholder-con-jurisdiccion="Seleccione un colegio emisor"
                                            required
                                            class="mt-2 block w-full rounded-xl border-slate-300"
                                        >
                                            <option value="">
                                                Primero seleccione una jurisdicción
                                            </option>

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

                                        <p class="mt-1.5 text-xs leading-5 text-slate-500">
                                            Solo se mostrarán colegios correspondientes a la
                                            jurisdicción elegida.
                                        </p>

                                        <x-input-error
                                            :messages="$errors->get('colegio_emisor_id')"
                                            class="mt-2"
                                        />
                                    </div>

                                    {{-- Número de matrícula --}}
                                    <div class="md:col-span-2">
                                        <label
                                            for="nro_matricula_profesional"
                                            class="block text-sm font-semibold text-slate-700"
                                        >
                                            Número de matrícula profesional
                                            <span class="text-rose-500" aria-hidden="true">*</span>
                                        </label>

                                        <input
                                            id="nro_matricula_profesional"
                                            name="nro_matricula_profesional"
                                            type="text"
                                            value="{{ old('nro_matricula_profesional') }}"
                                            placeholder="{{ $colegioSeleccionado
                                                ? 'Ej: 0763'
                                                : 'Seleccione primero un colegio emisor' }}"
                                            data-colegio-matricula-source="#colegio_emisor_id"
                                            data-colegio-matricula-jurisdiccion-source="#jurisdiccion_id"
                                            data-matricula-placeholder-sin-colegio="Seleccione primero un colegio emisor"
                                            data-matricula-placeholder-con-colegio="Ej: 0763"
                                            required
                                            @disabled(! $colegioSeleccionado)
                                            autocomplete="off"
                                            class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                                        >

                                        <p class="mt-1.5 text-xs leading-5 text-slate-500">
                                            Ingrese el número de matrícula tal como figura en su
                                            credencial profesional.
                                        </p>

                                        <x-input-error
                                            :messages="$errors->get('nro_matricula_profesional')"
                                            class="mt-2"
                                        />
                                    </div>
                                @endunless

                                {{-- Especialidades --}}
                                <div class="md:col-span-2">
                                    <label
                                        for="especialidades"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Especialidades
                                        <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>

                                    <select
                                        id="especialidades"
                                        name="especialidades[]"
                                        data-tom-select="especialidades"
                                        data-tom-select-placeholder="Seleccione una o más especialidades"
                                        multiple
                                        required
                                        class="mt-2 block w-full rounded-xl border-slate-300"
                                    >
                                        @foreach ($especialidades as $especialidad)
                                            <option
                                                value="{{ $especialidad->id }}"
                                                {{ in_array(
                                                    $especialidad->id,
                                                    $especialidadesSeleccionadas
                                                ) ? 'selected' : '' }}
                                            >
                                                {{ $especialidad->nombre }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <p class="mt-1.5 text-xs leading-5 text-slate-500">
                                        Puede buscar y seleccionar una o varias especialidades.
                                    </p>

                                    <x-input-error
                                        :messages="$errors->get('especialidades')"
                                        class="mt-2"
                                    />

                                    <x-input-error
                                        :messages="$errors->get('especialidades.*')"
                                        class="mt-2"
                                    />
                                </div>
                            </div>
                        </section>
                    </div>

                    {{-- Acciones --}}
                    <div class="border-t border-slate-200 bg-slate-50 px-6 py-5 sm:px-8">
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                @if ($esEdicion)
                                    <a
                                        href="{{ route('dashboard') }}"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                                    >
                                        Volver
                                    </a>
                                @else
                                    <button
                                        type="submit"
                                        form="logout-form"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                                    >
                                        Cerrar sesión
                                    </button>
                                @endif
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12l5 5L20 7"
                                    />
                                </svg>

                                {{ $esEdicion
                                    ? 'Guardar cambios'
                                    : 'Guardar y continuar' }}
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Pie --}}
                <div class="border-t border-slate-100 bg-white px-6 py-4 sm:px-8">
                    <div class="flex items-start justify-center gap-2 text-center text-xs leading-5 text-slate-500">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="mt-0.5 h-4 w-4 shrink-0"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5A1.5 1.5 0 0118.75 12v7.5A1.5 1.5 0 0117.25 21H6.75a1.5 1.5 0 01-1.5-1.5V12a1.5 1.5 0 011.5-1.5z"
                            />
                        </svg>

                        <span>
                            Sus datos se utilizan para la gestión de su perfil
                            profesional dentro de KinesioSoft.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .perfil-profesional-form .ts-wrapper {
            width: 100%;
            margin-top: 0.5rem;
        }

        .perfil-profesional-form .ts-control {
            min-height: 46px;
            border-radius: 0.75rem;
            border-color: rgb(203 213 225);
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow:
                0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .perfil-profesional-form .ts-wrapper.focus .ts-control {
            border-color: rgb(99 102 241);
            box-shadow:
                0 0 0 1px rgb(99 102 241);
        }

        .perfil-profesional-form .ts-wrapper.disabled .ts-control {
            cursor: not-allowed;
            background-color: rgb(241 245 249);
            color: rgb(100 116 139);
            opacity: 1;
        }

        .perfil-profesional-form .ts-dropdown {
            border-radius: 0.75rem;
            border-color: rgb(203 213 225);
            overflow: hidden;
            box-shadow:
                0 10px 15px -3px rgb(0 0 0 / 0.1),
                0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        .perfil-profesional-form .ts-dropdown .option {
            padding: 10px 12px;
        }

        .perfil-profesional-form .ts-dropdown-especialidades {
        overflow: hidden;
        border: 1px solid rgb(203 213 225);
        border-radius: 0.75rem;
        background-color: white;
        box-shadow:
            0 10px 15px -3px rgb(0 0 0 / 0.1),
            0 4px 6px -4px rgb(0 0 0 / 0.1);
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .ts-dropdown-content {
        max-height: 240px;
        overflow-y: auto;
        padding: 0.375rem;
        scrollbar-width: thin;
        scrollbar-color:
            rgb(148 163 184)
            transparent;
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .option {
        margin: 0.125rem 0;
        padding: 0.65rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: rgb(30 41 59);
        transition:
            background-color 150ms ease,
            color 150ms ease;
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .option:hover,
    .perfil-profesional-form
        .ts-dropdown-especialidades
        .option.active {
        background-color: rgb(238 242 255);
        color: rgb(55 48 163);
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .no-results {
        padding: 0.75rem;
        font-size: 0.875rem;
        color: rgb(100 116 139);
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .ts-dropdown-content::-webkit-scrollbar {
        width: 6px;
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .ts-dropdown-content::-webkit-scrollbar-track {
        background: transparent;
    }

    .perfil-profesional-form
        .ts-dropdown-especialidades
        .ts-dropdown-content::-webkit-scrollbar-thumb {
        background-color: rgb(148 163 184);
        border-radius: 9999px;
    }
    </style>
</x-app-layout>
