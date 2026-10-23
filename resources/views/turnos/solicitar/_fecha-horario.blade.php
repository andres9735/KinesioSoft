<section x-show="paso === 1" aria-labelledby="titulo-paso-1" class="space-y-5">
    <h2 id="titulo-paso-1" x-ref="tituloPaso1" tabindex="-1" class="rounded-lg text-xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Fecha y horario</h2>
    <p x-show="erroresServidor.inicio_at" x-cloak x-ref="errorInicio" tabindex="-1" x-text="erroresServidor.inicio_at" role="alert" class="rounded-lg text-sm text-red-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"></p>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm font-semibold text-gray-900"><span x-text="fechaLegible(ventana.desde)"></span> — <span x-text="fechaLegible(ventana.hasta)"></span></p>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                @click="cambiarVentana(-1)"
                :disabled="ventana.desde === @js($configuracionWizard['fechaInicial'])"
                class="min-h-11 rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
            >Semana anterior</button>
            <button type="button" @click="cambiarVentana(1)" class="min-h-11 rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">Semana siguiente</button>
        </div>
    </div>
    <div :aria-busy="estadoDisponibilidad === 'loading'" class="space-y-5">
        <p x-show="estadoDisponibilidad === 'loading'" x-cloak role="status" class="rounded-xl bg-indigo-50 p-5 text-sm text-indigo-900">Consultando horarios disponibles…</p>
        <div x-show="estadoDisponibilidad === 'empty'" x-cloak role="status" class="space-y-3 rounded-xl bg-gray-50 p-6 text-sm text-gray-700">
            <p>No hay horarios disponibles entre el <span x-text="fechaLegible(ventana.desde)"></span> y el <span x-text="fechaLegible(ventana.hasta)"></span>.</p>
            <p>Podés consultar otra semana.</p>
        </div>
        <div x-show="estadoDisponibilidad === 'error'" x-cloak x-ref="errorDisponibilidad" tabindex="-1" role="alert" class="space-y-3 rounded-xl bg-amber-50 p-5 text-sm text-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-600">
            <p x-text="errorDisponibilidad"></p>
            <button type="button" @click="consultarDisponibilidad()" class="min-h-11 rounded-lg border border-amber-300 bg-white px-4 py-2 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-600">Reintentar</button>
        </div>
        <div x-show="estadoDisponibilidad === 'success'" x-cloak class="space-y-5" aria-label="Horarios disponibles">
            <template x-for="grupo in diasDisponibles" :key="grupo.fecha">
                <fieldset class="space-y-3 rounded-xl border border-gray-200 p-4">
                    <legend class="px-2 text-sm font-semibold text-gray-900" x-text="fechaLegible(grupo.fecha)"></legend>
                    <div class="flex flex-wrap gap-3">
                        <template x-for="horario in grupo.horarios" :key="horario.inicio_at">
                            <button type="button" @click="seleccionarFecha(grupo.fecha); seleccionarInicio(horario.inicio_at)" :aria-label="fechaLegible(grupo.fecha) + ', ' + horaLegible(horario.inicio_at)" :aria-pressed="inicioSeleccionado === horario.inicio_at" class="min-h-11 rounded-lg border px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" :class="inicioSeleccionado === horario.inicio_at ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-800 hover:bg-indigo-50'">
                                <span x-text="horaLegible(horario.inicio_at)"></span>
                                <span x-show="inicioSeleccionado === horario.inicio_at" class="ml-1">· Seleccionado</span>
                            </button>
                        </template>
                    </div>
                </fieldset>
            </template>
            <button type="button" @click="consultarDisponibilidad()" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500">Actualizar horarios</button>
        </div>
    </div>
    <p class="text-xs text-gray-500">Seleccionar un horario no lo reserva. Se verificará nuevamente al registrar el turno.</p>
</section>
