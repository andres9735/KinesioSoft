<section x-show="paso === 3" x-cloak aria-labelledby="titulo-paso-3" class="space-y-5">
    <h2 id="titulo-paso-3" x-ref="tituloPaso3" tabindex="-1" class="text-xl font-semibold text-gray-900 focus:outline-none">Fecha y horario</h2>
    <p class="text-sm text-gray-700">Profesional: <span class="font-semibold" x-text="profesionalSeleccionada?.nombre || 'Sin seleccionar'"></span></p>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm font-semibold text-gray-900"><span x-text="fechaLegible(ventana.desde)"></span> — <span x-text="fechaLegible(ventana.hasta)"></span></p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="cambiarVentana(-1)" :disabled="ventana.desde === @js($configuracionWizard['fechaInicial'])" class="min-h-11 rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">Semana anterior</button>
            <button type="button" @click="cambiarVentana(1)" class="min-h-11 rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">Semana siguiente</button>
        </div>
    </div>
    <div :aria-busy="estadoDisponibilidad === 'loading'" class="space-y-5">
        <p x-show="estadoDisponibilidad === 'loading'" x-cloak role="status" class="rounded-xl bg-indigo-50 p-5 text-sm text-indigo-900">Consultando horarios disponibles…</p>
        <div x-show="estadoDisponibilidad === 'empty'" x-cloak role="status" class="space-y-3 rounded-xl bg-gray-50 p-6 text-sm text-gray-700">
            <p>No hay horarios disponibles entre el <span x-text="fechaLegible(ventana.desde)"></span> y el <span x-text="fechaLegible(ventana.hasta)"></span>.</p>
            <p>Podés consultar otra semana o elegir otra profesional.</p>
        </div>
        <div x-show="estadoDisponibilidad === 'error'" x-cloak role="alert" class="space-y-3 rounded-xl bg-amber-50 p-5 text-sm text-amber-900">
            <p x-text="errorDisponibilidad"></p>
            <button type="button" @click="consultarDisponibilidad()" class="min-h-11 rounded-lg border border-amber-300 bg-white px-4 py-2 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-600">Reintentar</button>
        </div>
        <div x-show="estadoDisponibilidad === 'success'" x-cloak class="space-y-5" aria-label="Horarios disponibles">
            <template x-for="grupo in gruposSlots" :key="grupo.fecha">
                <fieldset class="space-y-3 rounded-xl border border-gray-200 p-4">
                    <legend class="px-2 text-sm font-semibold text-gray-900" x-text="fechaLegible(grupo.fecha)"></legend>
                    <div class="flex flex-wrap gap-3">
                        <template x-for="slot in grupo.slots" :key="slot.inicio_at">
                            <button type="button" @click="seleccionarSlot(slot.inicio_at)" :aria-pressed="slotSeleccionado?.inicio_at === slot.inicio_at" class="min-h-11 rounded-lg border px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" :class="slotSeleccionado?.inicio_at === slot.inicio_at ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-800 hover:bg-indigo-50'">
                                <span x-text="horaLegible(slot.inicio_at) + ' – ' + horaLegible(slot.fin_at)"></span>
                                <span x-show="slotSeleccionado?.inicio_at === slot.inicio_at" class="ml-1">· Seleccionado</span>
                            </button>
                        </template>
                    </div>
                </fieldset>
            </template>
            <button type="button" @click="consultarDisponibilidad()" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500">Actualizar horarios</button>
        </div>
    </div>
    <p class="text-xs text-gray-500">Seleccionar un horario no lo reserva. Se verificará nuevamente al registrar el turno.</p>
    <button type="button" @click="anterior()" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500">Cambiar profesional</button>
</section>
