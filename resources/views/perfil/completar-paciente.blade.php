<x-app-layout>
    @php
        $user = auth()->user();
        $paciente = $user->paciente;

        $antecedentesRespuesta = old(
            'antecedentes_respuesta',
            'no'
        );

        $cirugiasRespuesta = old(
            'cirugias_respuesta',
            'no'
        );

        $alergiasRespuesta = old(
            'alergias_respuesta',
            'no'
        );

        $medicacionRespuesta = old(
            'medicacion_respuesta',
            'no'
        );

        $antecedentesIniciales = array_values(
            old('antecedentes', [])
        );

        $cirugiasIniciales = array_values(
            old('cirugias', [])
        );

        $alergiasIniciales = array_values(
            old('alergias', [])
        );

        $medicacionesIniciales = array_values(
            old('medicaciones', [])
        );

        if (
            $antecedentesRespuesta === 'si' &&
            $antecedentesIniciales === []
        ) {
            $antecedentesIniciales = [''];
        }

        if (
            $cirugiasRespuesta === 'si' &&
            $cirugiasIniciales === []
        ) {
            $cirugiasIniciales = [''];
        }

        if (
            $alergiasRespuesta === 'si' &&
            $alergiasIniciales === []
        ) {
            $alergiasIniciales = [''];
        }

        if (
            $medicacionRespuesta === 'si' &&
            $medicacionesIniciales === []
        ) {
            $medicacionesIniciales = [''];
        }

        $camposPaso1 = [
            'dni',
            'fecha_nacimiento',
            'sexo',
            'telefono',
            'direccion',
            'contacto_emergencia_nombre',
            'contacto_emergencia_telefono',
        ];

        $camposPaso2 = [
            'objetivo_salud',
            'peso_kg',
            'altura_cm',
            'ocupacion',
            'nivel_actividad_fisica',
            'detalle_actividad_fisica',
            'antecedentes_respuesta',
            'antecedentes',
            'cirugias_respuesta',
            'cirugias',
            'alergias_respuesta',
            'alergias',
            'medicacion_respuesta',
            'medicaciones',
        ];

        $camposConError = array_map(
            static fn (string $campo): string =>
                explode('.', $campo)[0],
            $errors->keys()
        );

        $tieneErroresPaso1 = array_intersect(
            $camposPaso1,
            $camposConError
        ) !== [];

        $tieneErroresPaso2 = array_intersect(
            $camposPaso2,
            $camposConError
        ) !== [];

        if (
            session()->has('warning') ||
            session()->has('error')
        ) {
            /*
             * El Request ya fue válido y el problema ocurrió durante
             * la operación final. Se restaura directamente la revisión.
             */
            $pasoInicial = 3;
        } elseif ($tieneErroresPaso1) {
            $pasoInicial = 1;
        } elseif ($tieneErroresPaso2) {
            $pasoInicial = 2;
        } else {
            $pasoInicial = 1;
        }
    @endphp

    <div
        x-data="{
            paso: @js($pasoInicial),
            validando: false,
            enviando: false,
            erroresValidacion: {},
            mensajeValidacion: '',
            resumen: {},

            antecedentesRespuesta: @js($antecedentesRespuesta),
            cirugiasRespuesta: @js($cirugiasRespuesta),
            alergiasRespuesta: @js($alergiasRespuesta),
            medicacionRespuesta: @js($medicacionRespuesta),

            antecedentes: @js($antecedentesIniciales),
            cirugias: @js($cirugiasIniciales),
            alergias: @js($alergiasIniciales),
            medicaciones: @js($medicacionesIniciales),

            irAlPaso(numero) {
                this.limpiarErroresValidacion();

                if (numero === 3) {
                    this.prepararResumen();
                }

                this.paso = numero;

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            },

            responder(categoria, respuesta) {
                const propiedadRespuesta =
                    categoria === 'medicaciones'
                        ? 'medicacionRespuesta'
                        : categoria + 'Respuesta';

                this[propiedadRespuesta] = respuesta;

                if (
                    respuesta === 'si' &&
                    this[categoria].length === 0
                ) {
                    this[categoria].push('');
                }

                if (respuesta === 'no') {
                    this[categoria] = [];
                }
            },

            agregarItem(categoria) {
                this[categoria].push('');
            },

            quitarItem(categoria, indice) {
                this[categoria].splice(indice, 1);

                const propiedadRespuesta =
                    categoria === 'medicaciones'
                        ? 'medicacionRespuesta'
                        : categoria + 'Respuesta';

                if (
                    this[propiedadRespuesta] === 'si' &&
                    this[categoria].length === 0
                ) {
                    this[categoria].push('');
                }
            },

            valorCampo(id) {
                const elemento = document.getElementById(id);

                return elemento
                    ? elemento.value.trim()
                    : '';
            },

            prepararResumen() {
                const formulario = document.getElementById(
                    'perfil-paciente-form'
                );

                if (! formulario) {
                    this.resumen = {};

                    return;
                }

                const datos = new FormData(formulario);

                this.resumen = {
                    dni: String(datos.get('dni') ?? '').trim(),
                    fecha_nacimiento: String(
                        datos.get('fecha_nacimiento') ?? ''
                    ).trim(),
                    sexo: String(datos.get('sexo') ?? '').trim(),
                    telefono: String(
                        datos.get('telefono') ?? ''
                    ).trim(),

                    direccion: String(
                        datos.get('direccion') ?? ''
                    ).trim(),

                    contacto_emergencia_nombre: String(
                        datos.get('contacto_emergencia_nombre') ?? ''
                    ).trim(),

                    contacto_emergencia_telefono: String(
                        datos.get('contacto_emergencia_telefono') ?? ''
                    ).trim(),

                    objetivo_salud: String(
                        datos.get('objetivo_salud') ?? ''
                    ).trim(),

                    peso_kg: String(
                        datos.get('peso_kg') ?? ''
                    ).trim(),

                    altura_cm: String(
                        datos.get('altura_cm') ?? ''
                    ).trim(),

                    ocupacion: String(
                        datos.get('ocupacion') ?? ''
                    ).trim(),

                    nivel_actividad_fisica: String(
                        datos.get('nivel_actividad_fisica') ?? ''
                    ).trim(),

                    detalle_actividad_fisica: String(
                        datos.get('detalle_actividad_fisica') ?? ''
                    ).trim(),
                };
            },

            textoSexo() {
                const valores = {
                    masculino: 'Masculino',
                    femenino: 'Femenino',
                    otro: 'Otro',
                    prefiero_no_decir: 'Prefiero no decir'
                };

                return valores[this.resumen.sexo]
                    ?? 'No informado';
            },

            textoActividad() {
                const valores = {
                    SEDENTARIO: 'Sedentario',
                    MODERADO: 'Moderado',
                    ACTIVO: 'Activo'
                };

                return valores[
                    this.resumen.nivel_actividad_fisica
                ] ?? 'No informado';
            },

            textoOValor(valor, sufijo = '') {
                if (! valor) {
                    return 'No informado';
                }

                return sufijo
                    ? valor + ' ' + sufijo
                    : valor;
            },

            limpiarErroresValidacion() {
                this.erroresValidacion = {};
                this.mensajeValidacion = '';
            },

            async validarPaso(numero) {
                if (this.validando) {
                    return;
                }

                this.limpiarErroresValidacion();
                this.validando = true;

                const formulario = document.getElementById(
                    'perfil-paciente-form'
                );

                const url = numero === 1
                    ? @js(route('perfil.validar-paso-1'))
                    : @js(route('perfil.validar-paso-2'));

                try {
                    const respuesta = await fetch(url, {
                        method: 'POST',
                        body: new FormData(formulario),
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (respuesta.ok) {
                        this.irAlPaso(numero + 1);

                        return;
                    }

                    if (respuesta.status === 422) {
                        const datos = await respuesta.json();

                        this.erroresValidacion =
                            datos.errors ?? {};

                        this.mensajeValidacion =
                            'Revise los campos indicados antes de continuar.';

                        this.enfocarPrimerError();

                        return;
                    }

                    this.mensajeValidacion =
                        'No se pudo validar la información. Intente nuevamente.';
                } catch (error) {
                    console.error(error);

                    this.mensajeValidacion =
                        'No se pudo validar la información. Verifique su conexión e intente nuevamente.';
                } finally {
                    this.validando = false;
                }
            },

            enfocarPrimerError() {
                const primerCampo = Object.keys(
                    this.erroresValidacion
                )[0];

                if (! primerCampo) {
                    return;
                }

                const campoBase = primerCampo.split('.')[0];

                const elemento = document.querySelector(
                    `[name='${campoBase}'], [name='${campoBase}[]']`
                );

                if (! elemento) {
                    return;
                }

                window.setTimeout(() => {
                    elemento.focus();

                    elemento.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 50);
            },

            erroresDe(campo) {
                const mensajes = [];

                Object.entries(this.erroresValidacion)
                    .forEach(([clave, errores]) => {
                        if (
                            clave === campo ||
                            clave.startsWith(campo + '.')
                        ) {
                            mensajes.push(...errores);
                        }
                    });

                return mensajes;
            },

            enviarFormularioFinal(evento) {
                if (
                    this.enviando ||
                    this.paso !== 3
                ) {
                    return;
                }

                this.limpiarErroresValidacion();

                this.enviando = true;

                /*
                 * submit() nativo evita volver a disparar el listener
                 * Alpine @submit.prevent.
                 *
                 * La validación definitiva vuelve a ejecutarse igualmente
                 * en CompletarPerfilPacienteRequest del servidor.
                 */
                evento.currentTarget.submit();
            }
        }"
        x-init="$nextTick(() => {
            if (paso === 3) {
                prepararResumen();
            }
        })"
        class="min-h-[calc(100vh-4rem)] bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
    >
        <div class="mx-auto max-w-[900px]">
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
                                Completar perfil clínico inicial
                            </h1>

                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                                Complete sus datos personales y su declaración inicial
                                de salud para configurar su perfil en KinesioSoft.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Stepper --}}
                <div class="border-b border-slate-200 bg-white px-6 py-6 sm:px-8">
                    <div class="grid grid-cols-3">
                        {{-- Paso 1 --}}
                        <div class="relative flex flex-col items-center text-center">
                            <div
                                class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold ring-4 ring-white transition"
                                :class="paso >= 1
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-200 text-slate-500'"
                            >
                                1
                            </div>

                            <p
                                class="mt-2 text-xs font-semibold sm:text-sm"
                                :class="paso >= 1
                                    ? 'text-indigo-700'
                                    : 'text-slate-500'"
                            >
                                Datos personales
                            </p>

                            <div
                                class="absolute left-1/2 top-5 -z-0 h-0.5 w-full"
                                :class="paso >= 2
                                    ? 'bg-indigo-600'
                                    : 'bg-slate-200'"
                            ></div>
                        </div>

                        {{-- Paso 2 --}}
                        <div class="relative flex flex-col items-center text-center">
                            <div
                                class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold ring-4 ring-white transition"
                                :class="paso >= 2
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-200 text-slate-500'"
                            >
                                2
                            </div>

                            <p
                                class="mt-2 text-xs font-semibold sm:text-sm"
                                :class="paso >= 2
                                    ? 'text-indigo-700'
                                    : 'text-slate-500'"
                            >
                                Declaración de salud
                            </p>

                            <div
                                class="absolute left-1/2 top-5 -z-0 h-0.5 w-full"
                                :class="paso >= 3
                                    ? 'bg-indigo-600'
                                    : 'bg-slate-200'"
                            ></div>
                        </div>

                        {{-- Paso 3 --}}
                        <div class="relative flex flex-col items-center text-center">
                            <div
                                class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold ring-4 ring-white transition"
                                :class="paso >= 3
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-200 text-slate-500'"
                            >
                                3
                            </div>

                            <p
                                class="mt-2 text-xs font-semibold sm:text-sm"
                                :class="paso >= 3
                                    ? 'text-indigo-700'
                                    : 'text-slate-500'"
                            >
                                Revisar y confirmar
                            </p>
                        </div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('perfil.actualizar') }}"
                    id="perfil-paciente-form"
                    @submit.prevent="enviarFormularioFinal($event)"
                >
                    @csrf

                    <div
                        x-show="mensajeValidacion"
                        x-cloak
                        class="mx-6 mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 sm:mx-8"
                    >
                        <div class="flex gap-3">
                            <div class="shrink-0 text-rose-600">
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
                                        d="M12 9v3.75m0 3.75h.008v.008H12V16.5zm9-4.5a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-sm font-semibold text-rose-900"
                                    x-text="mensajeValidacion"
                                ></p>

                                <ul
                                    x-show="Object.keys(erroresValidacion).length > 0"
                                    class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-800"
                                >
                                    <template
                                        x-for="(mensajes, campo) in erroresValidacion"
                                        :key="campo"
                                    >
                                        <template
                                            x-for="mensaje in mensajes"
                                            :key="mensaje"
                                        >
                                            <li x-text="mensaje"></li>
                                        </template>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-8 sm:px-8 sm:py-10">

                        {{-- PASO 1 --}}
                        <section
                            x-show="paso === 1"
                            x-cloak
                        >
                            <div class="mb-7">
                                <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
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
                                            Datos personales y de contacto
                                        </h2>

                                        <p class="text-sm text-slate-500">
                                            Información necesaria para identificarlo y
                                            contactarlo correctamente.
                                        </p>
                                    </div>
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
                                        <span class="text-rose-500">*</span>
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

                                    <p class="mt-1.5 text-xs text-slate-500">
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
                                        <span class="text-rose-500">*</span>
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
                                        <span class="text-rose-500">*</span>
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
                                        <span class="text-rose-500">*</span>
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

                                    <x-input-error
                                        :messages="$errors->get('telefono')"
                                        class="mt-2"
                                    />
                                </div>

                                {{-- Dirección --}}
                                <div class="md:col-span-2">
                                    <label
                                        for="direccion"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Dirección
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="direccion"
                                        name="direccion"
                                        type="text"
                                        value="{{ old('direccion', $paciente?->direccion) }}"
                                        placeholder="Ej: Av. Mitre 1234"
                                        required
                                        autocomplete="street-address"
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <x-input-error
                                        :messages="$errors->get('direccion')"
                                        class="mt-2"
                                    />
                                </div>

                                {{-- Contacto de emergencia --}}
                                <div class="md:col-span-2 mt-2">
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                                        <h3 class="text-sm font-bold text-amber-950">
                                            Contacto de emergencia
                                        </h3>

                                        <p class="mt-1 text-sm leading-6 text-amber-800">
                                            Indique una persona a quien podamos contactar
                                            ante una situación que lo requiera.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        for="contacto_emergencia_nombre"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Nombre y apellido
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="contacto_emergencia_nombre"
                                        name="contacto_emergencia_nombre"
                                        type="text"
                                        value="{{ old(
                                            'contacto_emergencia_nombre',
                                            $paciente?->contacto_emergencia_nombre
                                        ) }}"
                                        placeholder="Ej: María Pérez"
                                        required
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <x-input-error
                                        :messages="$errors->get('contacto_emergencia_nombre')"
                                        class="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="contacto_emergencia_telefono"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Teléfono
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="contacto_emergencia_telefono"
                                        name="contacto_emergencia_telefono"
                                        type="text"
                                        value="{{ old(
                                            'contacto_emergencia_telefono',
                                            $paciente?->contacto_emergencia_telefono
                                        ) }}"
                                        placeholder="Ej: 3764 123456"
                                        required
                                        autocomplete="tel"
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <x-input-error
                                        :messages="$errors->get('contacto_emergencia_telefono')"
                                        class="mt-2"
                                    />
                                </div>
                            </div>
                        </section>

                        {{-- PASO 2 --}}
                        <section
                            x-show="paso === 2"
                            x-cloak
                        >
                            <div class="mb-7">
                                <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
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
                                                d="M9 12.75l2.25 2.25L15 9.75M12 3c-4.5 0-8.25 3.75-8.25 8.25S7.5 19.5 12 21c4.5-1.5 8.25-5.25 8.25-9.75S16.5 3 12 3z"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <h2 class="text-lg font-bold text-slate-900">
                                            Declaración inicial de salud
                                        </h2>

                                        <p class="text-sm text-slate-500">
                                            Registre información general sobre su estado de salud
                                            y actividad habitual.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-7 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                                <div class="flex gap-3">
                                    <div class="mt-0.5 shrink-0 text-blue-700">
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
                                        <p class="text-sm font-bold text-blue-950">
                                            Información declarada por el paciente
                                        </p>

                                        <p class="mt-1 text-sm leading-6 text-blue-800">
                                            Estos datos quedarán pendientes de revisión profesional
                                            y no se incorporan automáticamente a su Historia Clínica.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Datos generales --}}
                            <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label
                                        for="objetivo_salud"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Objetivo de salud
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <textarea
                                        id="objetivo_salud"
                                        name="objetivo_salud"
                                        rows="3"
                                        required
                                        placeholder="Ej: Disminuir el dolor lumbar y recuperar movilidad."
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >{{ old('objetivo_salud') }}</textarea>

                                    <x-input-error
                                        :messages="$errors->get('objetivo_salud')"
                                        class="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="peso_kg"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Peso
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <div class="relative mt-2">
                                        <input
                                            id="peso_kg"
                                            name="peso_kg"
                                            type="number"
                                            value="{{ old('peso_kg') }}"
                                            min="1"
                                            max="500"
                                            step="0.01"
                                            required
                                            placeholder="Ej: 72.50"
                                            class="block w-full rounded-xl border-slate-300 px-4 py-2.5 pr-12 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                        >

                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-slate-500">
                                            kg
                                        </span>
                                    </div>

                                    <x-input-error
                                        :messages="$errors->get('peso_kg')"
                                        class="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="altura_cm"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Altura
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <div class="relative mt-2">
                                        <input
                                            id="altura_cm"
                                            name="altura_cm"
                                            type="number"
                                            value="{{ old('altura_cm') }}"
                                            min="30"
                                            max="250"
                                            step="0.01"
                                            required
                                            placeholder="Ej: 175"
                                            class="block w-full rounded-xl border-slate-300 px-4 py-2.5 pr-12 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                        >

                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-slate-500">
                                            cm
                                        </span>
                                    </div>

                                    <x-input-error
                                        :messages="$errors->get('altura_cm')"
                                        class="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="ocupacion"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Ocupación
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="ocupacion"
                                        name="ocupacion"
                                        type="text"
                                        value="{{ old('ocupacion') }}"
                                        maxlength="255"
                                        required
                                        placeholder="Ej: Docente"
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >

                                    <x-input-error
                                        :messages="$errors->get('ocupacion')"
                                        class="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        for="nivel_actividad_fisica"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Nivel de actividad física
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <select
                                        id="nivel_actividad_fisica"
                                        name="nivel_actividad_fisica"
                                        required
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">
                                            Seleccione una opción
                                        </option>

                                        <option
                                            value="SEDENTARIO"
                                            {{ old('nivel_actividad_fisica') === 'SEDENTARIO' ? 'selected' : '' }}
                                        >
                                            Sedentario
                                        </option>

                                        <option
                                            value="MODERADO"
                                            {{ old('nivel_actividad_fisica') === 'MODERADO' ? 'selected' : '' }}
                                        >
                                            Moderado
                                        </option>

                                        <option
                                            value="ACTIVO"
                                            {{ old('nivel_actividad_fisica') === 'ACTIVO' ? 'selected' : '' }}
                                        >
                                            Activo
                                        </option>
                                    </select>

                                    <x-input-error
                                        :messages="$errors->get('nivel_actividad_fisica')"
                                        class="mt-2"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label
                                        for="detalle_actividad_fisica"
                                        class="block text-sm font-semibold text-slate-700"
                                    >
                                        Detalle de actividad física
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <textarea
                                        id="detalle_actividad_fisica"
                                        name="detalle_actividad_fisica"
                                        rows="3"
                                        required
                                        placeholder="Ej: Caminatas de 30 minutos tres veces por semana."
                                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    >{{ old('detalle_actividad_fisica') }}</textarea>

                                    <x-input-error
                                        :messages="$errors->get('detalle_actividad_fisica')"
                                        class="mt-2"
                                    />
                                </div>
                            </div>

                            {{-- Información clínica declarada --}}
                            <div class="mt-10">
                                <div class="mb-5 border-b border-slate-200 pb-3">
                                    <h3 class="text-base font-bold text-slate-900">
                                        Información de salud
                                    </h3>

                                    <p class="mt-1 text-sm text-slate-500">
                                        Responda cada categoría y agregue los detalles
                                        correspondientes cuando seleccione Sí.
                                    </p>
                                </div>

                                <div class="space-y-6">

                                    {{-- Antecedentes --}}
                                    <div class="rounded-2xl border border-slate-200 p-5">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="font-semibold text-slate-900">
                                                    Antecedentes
                                                </h4>

                                                <p class="mt-1 text-sm text-slate-500">
                                                    ¿Posee antecedentes de salud relevantes?
                                                </p>
                                            </div>

                                            <div class="flex gap-2">
                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="antecedentes_respuesta"
                                                        value="si"
                                                        x-model="antecedentesRespuesta"
                                                        @change="responder('antecedentes', 'si')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        Sí
                                                    </span>
                                                </label>

                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="antecedentes_respuesta"
                                                        value="no"
                                                        x-model="antecedentesRespuesta"
                                                        @change="responder('antecedentes', 'no')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        No
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div
                                            x-show="antecedentesRespuesta === 'si'"
                                            x-cloak
                                            class="mt-5 space-y-3"
                                        >
                                            <template
                                                x-for="(item, indice) in antecedentes"
                                                :key="'antecedente-' + indice"
                                            >
                                                <div class="flex items-start gap-2">
                                                    <input
                                                        type="text"
                                                        name="antecedentes[]"
                                                        x-model="antecedentes[indice]"
                                                        :required="antecedentesRespuesta === 'si'"
                                                        placeholder="Describa el antecedente"
                                                        class="block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >

                                                    <button
                                                        type="button"
                                                        @click="quitarItem('antecedentes', indice)"
                                                        class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                                                        aria-label="Eliminar antecedente"
                                                    >
                                                        &times;
                                                    </button>
                                                </div>
                                            </template>

                                            <button
                                                type="button"
                                                @click="agregarItem('antecedentes')"
                                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                            >
                                                + Agregar otro antecedente
                                            </button>
                                        </div>

                                        <x-input-error
                                            :messages="$errors->get('antecedentes')"
                                            class="mt-2"
                                        />

                                        <x-input-error
                                            :messages="$errors->get('antecedentes.*')"
                                            class="mt-2"
                                        />
                                    </div>

                                    {{-- Cirugías --}}
                                    <div class="rounded-2xl border border-slate-200 p-5">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="font-semibold text-slate-900">
                                                    Cirugías
                                                </h4>

                                                <p class="mt-1 text-sm text-slate-500">
                                                    ¿Tuvo cirugías previamente?
                                                </p>
                                            </div>

                                            <div class="flex gap-2">
                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="cirugias_respuesta"
                                                        value="si"
                                                        x-model="cirugiasRespuesta"
                                                        @change="responder('cirugias', 'si')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        Sí
                                                    </span>
                                                </label>

                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="cirugias_respuesta"
                                                        value="no"
                                                        x-model="cirugiasRespuesta"
                                                        @change="responder('cirugias', 'no')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        No
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div
                                            x-show="cirugiasRespuesta === 'si'"
                                            x-cloak
                                            class="mt-5 space-y-3"
                                        >
                                            <template
                                                x-for="(item, indice) in cirugias"
                                                :key="'cirugia-' + indice"
                                            >
                                                <div class="flex items-start gap-2">
                                                    <input
                                                        type="text"
                                                        name="cirugias[]"
                                                        x-model="cirugias[indice]"
                                                        :required="cirugiasRespuesta === 'si'"
                                                        placeholder="Describa la cirugía"
                                                        class="block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >

                                                    <button
                                                        type="button"
                                                        @click="quitarItem('cirugias', indice)"
                                                        class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                                                        aria-label="Eliminar cirugía"
                                                    >
                                                        &times;
                                                    </button>
                                                </div>
                                            </template>

                                            <button
                                                type="button"
                                                @click="agregarItem('cirugias')"
                                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                            >
                                                + Agregar otra cirugía
                                            </button>
                                        </div>

                                        <x-input-error
                                            :messages="$errors->get('cirugias')"
                                            class="mt-2"
                                        />

                                        <x-input-error
                                            :messages="$errors->get('cirugias.*')"
                                            class="mt-2"
                                        />
                                    </div>

                                    {{-- Alergias --}}
                                    <div class="rounded-2xl border border-slate-200 p-5">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="font-semibold text-slate-900">
                                                    Alergias
                                                </h4>

                                                <p class="mt-1 text-sm text-slate-500">
                                                    ¿Posee alguna alergia conocida?
                                                </p>
                                            </div>

                                            <div class="flex gap-2">
                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="alergias_respuesta"
                                                        value="si"
                                                        x-model="alergiasRespuesta"
                                                        @change="responder('alergias', 'si')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        Sí
                                                    </span>
                                                </label>

                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="alergias_respuesta"
                                                        value="no"
                                                        x-model="alergiasRespuesta"
                                                        @change="responder('alergias', 'no')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        No
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div
                                            x-show="alergiasRespuesta === 'si'"
                                            x-cloak
                                            class="mt-5 space-y-3"
                                        >
                                            <template
                                                x-for="(item, indice) in alergias"
                                                :key="'alergia-' + indice"
                                            >
                                                <div class="flex items-start gap-2">
                                                    <input
                                                        type="text"
                                                        name="alergias[]"
                                                        x-model="alergias[indice]"
                                                        :required="alergiasRespuesta === 'si'"
                                                        placeholder="Describa la alergia"
                                                        class="block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >

                                                    <button
                                                        type="button"
                                                        @click="quitarItem('alergias', indice)"
                                                        class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                                                        aria-label="Eliminar alergia"
                                                    >
                                                        &times;
                                                    </button>
                                                </div>
                                            </template>

                                            <button
                                                type="button"
                                                @click="agregarItem('alergias')"
                                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                            >
                                                + Agregar otra alergia
                                            </button>
                                        </div>

                                        <x-input-error
                                            :messages="$errors->get('alergias')"
                                            class="mt-2"
                                        />

                                        <x-input-error
                                            :messages="$errors->get('alergias.*')"
                                            class="mt-2"
                                        />
                                    </div>

                                    {{-- Medicación --}}
                                    <div class="rounded-2xl border border-slate-200 p-5">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="font-semibold text-slate-900">
                                                    Medicación habitual
                                                </h4>

                                                <p class="mt-1 text-sm text-slate-500">
                                                    ¿Consume actualmente algún medicamento?
                                                </p>
                                            </div>

                                            <div class="flex gap-2">
                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="medicacion_respuesta"
                                                        value="si"
                                                        x-model="medicacionRespuesta"
                                                        @change="responder('medicaciones', 'si')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        Sí
                                                    </span>
                                                </label>

                                                <label class="cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="medicacion_respuesta"
                                                        value="no"
                                                        x-model="medicacionRespuesta"
                                                        @change="responder('medicaciones', 'no')"
                                                        class="peer sr-only"
                                                        required
                                                    >

                                                    <span class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                                        No
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div
                                            x-show="medicacionRespuesta === 'si'"
                                            x-cloak
                                            class="mt-5 space-y-3"
                                        >
                                            <template
                                                x-for="(item, indice) in medicaciones"
                                                :key="'medicacion-' + indice"
                                            >
                                                <div class="flex items-start gap-2">
                                                    <input
                                                        type="text"
                                                        name="medicaciones[]"
                                                        x-model="medicaciones[indice]"
                                                        :required="medicacionRespuesta === 'si'"
                                                        placeholder="Indique el medicamento"
                                                        class="block w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >

                                                    <button
                                                        type="button"
                                                        @click="quitarItem('medicaciones', indice)"
                                                        class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                                                        aria-label="Eliminar medicación"
                                                    >
                                                        &times;
                                                    </button>
                                                </div>
                                            </template>

                                            <button
                                                type="button"
                                                @click="agregarItem('medicaciones')"
                                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                            >
                                                + Agregar otro medicamento
                                            </button>
                                        </div>

                                        <x-input-error
                                            :messages="$errors->get('medicaciones')"
                                            class="mt-2"
                                        />

                                        <x-input-error
                                            :messages="$errors->get('medicaciones.*')"
                                            class="mt-2"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- PASO 3 --}}
                        <section
                            x-show="paso === 3"
                            x-cloak
                        >
                            <div class="mb-7">
                                <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
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
                                                d="M9 12.75l2.25 2.25L15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <h2 class="text-lg font-bold text-slate-900">
                                            Revisar y confirmar
                                        </h2>

                                        <p class="text-sm text-slate-500">
                                            Revise la información ingresada antes de completar
                                            su perfil clínico inicial.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-7">

                                {{-- Datos personales --}}
                                <div class="overflow-hidden rounded-2xl border border-slate-200">
                                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                                        <h3 class="font-bold text-slate-900">
                                            Datos personales y de contacto
                                        </h3>
                                    </div>

                                    <dl class="grid grid-cols-1 gap-x-6 gap-y-5 p-5 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                DNI
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.dni)"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Fecha de nacimiento
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.fecha_nacimiento)"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Sexo
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoSexo()"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Teléfono
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.telefono)"
                                            ></dd>
                                        </div>

                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Dirección
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.direccion)"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Contacto de emergencia
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.contacto_emergencia_nombre)"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Teléfono de emergencia
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.contacto_emergencia_telefono)"
                                            ></dd>
                                        </div>
                                    </dl>
                                </div>

                                {{-- Datos generales de salud --}}
                                <div class="overflow-hidden rounded-2xl border border-slate-200">
                                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                                        <h3 class="font-bold text-slate-900">
                                            Declaración inicial de salud
                                        </h3>
                                    </div>

                                    <dl class="grid grid-cols-1 gap-x-6 gap-y-5 p-5 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Objetivo de salud
                                            </dt>

                                            <dd
                                                class="mt-1 whitespace-pre-line text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.objetivo_salud)"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Peso
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(
                                                    resumen.peso_kg,
                                                    'kg'
                                                )"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Altura
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(
                                                    resumen.altura_cm,
                                                    'cm'
                                                )"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Ocupación
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoOValor(resumen.ocupacion)"
                                            ></dd>
                                        </div>

                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Nivel de actividad física
                                            </dt>

                                            <dd
                                                class="mt-1 text-sm font-medium text-slate-900"
                                                x-text="textoActividad()"
                                            ></dd>
                                        </div>

                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Detalle de actividad física
                                            </dt>

                                            <dd
                                                class="mt-1 whitespace-pre-line text-sm font-medium text-slate-900"
                                                x-text="textoOValor(
                                                    resumen.detalle_actividad_fisica
                                                )"
                                            ></dd>
                                        </div>
                                    </dl>
                                </div>

                                {{-- Información clínica --}}
                                <div class="overflow-hidden rounded-2xl border border-slate-200">
                                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                                        <h3 class="font-bold text-slate-900">
                                            Información de salud declarada
                                        </h3>
                                    </div>

                                    <div class="divide-y divide-slate-200">

                                        {{-- Antecedentes --}}
                                        <div class="p-5">
                                            <div class="flex items-center justify-between gap-4">
                                                <h4 class="text-sm font-semibold text-slate-900">
                                                    Antecedentes
                                                </h4>

                                                <span
                                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                    :class="antecedentesRespuesta === 'si'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : 'bg-emerald-100 text-emerald-800'"
                                                    x-text="antecedentesRespuesta === 'si'
                                                        ? 'Sí'
                                                        : 'No'"
                                                ></span>
                                            </div>

                                            <template x-if="antecedentesRespuesta === 'si'">
                                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    <template
                                                        x-for="(item, indice) in antecedentes"
                                                        :key="'revision-antecedente-' + indice"
                                                    >
                                                        <li x-text="item || 'Sin detalle'"></li>
                                                    </template>
                                                </ul>
                                            </template>
                                        </div>

                                        {{-- Cirugías --}}
                                        <div class="p-5">
                                            <div class="flex items-center justify-between gap-4">
                                                <h4 class="text-sm font-semibold text-slate-900">
                                                    Cirugías
                                                </h4>

                                                <span
                                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                    :class="cirugiasRespuesta === 'si'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : 'bg-emerald-100 text-emerald-800'"
                                                    x-text="cirugiasRespuesta === 'si'
                                                        ? 'Sí'
                                                        : 'No'"
                                                ></span>
                                            </div>

                                            <template x-if="cirugiasRespuesta === 'si'">
                                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    <template
                                                        x-for="(item, indice) in cirugias"
                                                        :key="'revision-cirugia-' + indice"
                                                    >
                                                        <li x-text="item || 'Sin detalle'"></li>
                                                    </template>
                                                </ul>
                                            </template>
                                        </div>

                                        {{-- Alergias --}}
                                        <div class="p-5">
                                            <div class="flex items-center justify-between gap-4">
                                                <h4 class="text-sm font-semibold text-slate-900">
                                                    Alergias
                                                </h4>

                                                <span
                                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                    :class="alergiasRespuesta === 'si'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : 'bg-emerald-100 text-emerald-800'"
                                                    x-text="alergiasRespuesta === 'si'
                                                        ? 'Sí'
                                                        : 'No'"
                                                ></span>
                                            </div>

                                            <template x-if="alergiasRespuesta === 'si'">
                                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    <template
                                                        x-for="(item, indice) in alergias"
                                                        :key="'revision-alergia-' + indice"
                                                    >
                                                        <li x-text="item || 'Sin detalle'"></li>
                                                    </template>
                                                </ul>
                                            </template>
                                        </div>

                                        {{-- Medicación --}}
                                        <div class="p-5">
                                            <div class="flex items-center justify-between gap-4">
                                                <h4 class="text-sm font-semibold text-slate-900">
                                                    Medicación habitual
                                                </h4>

                                                <span
                                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                    :class="medicacionRespuesta === 'si'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : 'bg-emerald-100 text-emerald-800'"
                                                    x-text="medicacionRespuesta === 'si'
                                                        ? 'Sí'
                                                        : 'No'"
                                                ></span>
                                            </div>

                                            <template x-if="medicacionRespuesta === 'si'">
                                                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                                    <template
                                                        x-for="(item, indice) in medicaciones"
                                                        :key="'revision-medicacion-' + indice"
                                                    >
                                                        <li x-text="item || 'Sin detalle'"></li>
                                                    </template>
                                                </ul>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- Aviso final --}}
                                <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                                    <div class="flex gap-3">
                                        <div class="mt-0.5 shrink-0 text-indigo-700">
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
                                                    d="M12 9v3.75m0 3.75h.008v.008H12V16.5zm9-4.5a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                        </div>

                                        <div>
                                            <p class="text-sm font-bold text-indigo-950">
                                                Antes de confirmar
                                            </p>

                                            <p class="mt-1 text-sm leading-6 text-indigo-800">
                                                Al finalizar, se registrará una declaración de salud
                                                pendiente de revisión profesional. Esta declaración
                                                no constituye todavía su Historia Clínica oficial.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    {{-- Navegación --}}
                    <div class="border-t border-slate-200 bg-slate-50 px-6 py-5 sm:px-8">
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                x-show="paso > 1"
                                x-cloak
                                type="button"
                                @click="irAlPaso(paso - 1)"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                            >
                                Anterior
                            </button>

                            <div
                                x-show="paso === 1"
                                class="sm:ml-auto"
                            >
                                <button
                                    type="button"
                                    @click="validarPaso(1)"
                                    :disabled="validando"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                                >
                                    <span
                                        x-text="validando
                                            ? 'Validando...'
                                            : 'Continuar'"
                                    ></span>

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
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <div
                                x-show="paso === 2"
                                x-cloak
                                class="sm:ml-auto"
                            >
                                <button
                                    type="button"
                                    @click="validarPaso(2)"
                                    :disabled="validando"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                                >
                                    <span
                                        x-text="validando
                                            ? 'Validando...'
                                            : 'Continuar'"
                                    ></span>

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
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <div
                                x-show="paso === 3"
                                x-cloak
                                class="sm:ml-auto"
                            >
                                <button
                                    type="submit"
                                    :disabled="enviando"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                                >
                                    <svg
                                        x-show="! enviando"
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

                                    <svg
                                        x-show="enviando"
                                        x-cloak
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        class="h-4 w-4 animate-spin"
                                        aria-hidden="true"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        ></circle>

                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M12 3a9 9 0 00-9 9H0A12 12 0 0112 0v3z"
                                        ></path>
                                    </svg>

                                    <span
                                        x-text="enviando
                                            ? 'Guardando...'
                                            : 'Confirmar y finalizar'"
                                    ></span>
                                </button>
                            </div>
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
                            Su declaración inicial de salud será revisada por un
                            profesional y no reemplaza la Historia Clínica oficial.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</x-app-layout>
