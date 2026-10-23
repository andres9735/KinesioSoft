<section x-show="paso === 2" x-cloak aria-labelledby="titulo-paso-2" class="min-w-0 space-y-6">
    <div class="space-y-2">
        <h2 id="titulo-paso-2" x-ref="tituloPaso2" tabindex="-1" class="rounded-lg text-2xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Elegir profesional</h2>
        <p class="text-sm leading-6 text-gray-600">Te mostramos sólo las profesionales que tienen disponibilidad para el horario seleccionado. La hora de finalización puede variar según la duración configurada para cada agenda.</p>
    </div>

    <div class="flex flex-wrap items-center gap-4 rounded-xl bg-indigo-50 p-4 text-sm" aria-label="Fecha y hora elegidas">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-medium uppercase text-gray-600">Fecha seleccionada</p>
            <p class="mt-1 font-semibold text-gray-900" x-text="fechaSeleccionada ? fechaLegible(fechaSeleccionada) : 'Sin seleccionar'"></p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase text-gray-600">Hora de inicio</p>
            <p class="mt-1 font-semibold text-gray-900" x-text="inicioSeleccionado ? horaLegible(inicioSeleccionado) : 'Sin seleccionar'"></p>
        </div>
        <button type="button" @click="anterior()" :disabled="bloqueado" class="min-h-11 rounded-lg px-3 py-2 font-semibold text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Cambiar fecha y horario</button>
    </div>

    <div :aria-busy="estadoProfesionales === 'loading'" class="min-w-0 space-y-4">
        <p x-show="estadoProfesionales === 'idle'" role="status" class="rounded-xl bg-gray-50 p-6 text-sm text-gray-700">Las profesionales se consultan después de seleccionar una fecha y un horario.</p>
        <p x-show="estadoProfesionales === 'loading'" x-cloak role="status" aria-live="polite" class="rounded-xl bg-indigo-50 p-6 text-sm text-indigo-900">Consultando profesionales disponibles…</p>
        <div x-show="estadoProfesionales === 'empty' && inicioProfesionales === inicioSeleccionado" x-cloak role="status" class="space-y-3 rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-700">
            <p class="font-semibold text-gray-900">No quedan profesionales disponibles para este horario de inicio.</p>
            <p>Podés volver al Paso 1 y elegir otra fecha u horario.</p>
            <button type="button" @click="anterior()" :disabled="bloqueado" class="min-h-11 rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Elegir otro horario</button>
        </div>
        <div x-show="estadoProfesionales === 'error'" x-cloak x-ref="errorProfesionales" tabindex="-1" role="alert" class="space-y-3 rounded-xl bg-amber-50 p-6 text-sm text-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-600">
            <p x-text="errorConsultaProfesionales"></p>
            <button type="button" @click="cargarProfesionalesDisponibles()" :disabled="bloqueado || !puedeElegirProfesional" class="min-h-11 rounded-lg border border-amber-300 bg-white px-4 py-2 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-600 disabled:opacity-50">Reintentar</button>
        </div>

        <fieldset x-show="estadoProfesionales === 'success' && inicioSeleccionado !== null && inicioProfesionales === inicioSeleccionado" x-cloak :disabled="bloqueado || estadoProfesionales !== 'success' || inicioProfesionales !== inicioSeleccionado" aria-describedby="profesional-error" class="min-w-0 space-y-3">
            <legend class="sr-only">Profesionales disponibles para el inicio seleccionado</legend>
            <template x-for="profesional in profesionales" :key="profesional.kinesiologa_id">
                <button type="button" @click="seleccionarProfesional(profesional.kinesiologa_id)" :aria-pressed="kinesiologaId === profesional.kinesiologa_id" :aria-label="inicioSeleccionado ? profesional.nombre + ', ' + horaLegible(inicioSeleccionado) + ' a ' + horaLegible(profesional.fin_at) + ', ' + duracionLegible(inicioSeleccionado, profesional.fin_at) : profesional.nombre" :aria-invalid="errorProfesional !== '' || !!erroresServidor.kinesiologa_id" aria-describedby="profesional-error" class="flex min-h-24 w-full min-w-0 flex-col gap-4 rounded-xl border p-4 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed sm:flex-row sm:items-center sm:p-5" :class="kinesiologaId === profesional.kinesiologa_id ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-transparent bg-white hover:border-indigo-200 hover:bg-gray-50'">
                    <span class="flex min-w-0 flex-1 items-center gap-3">
                        <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700" x-text="inicialesProfesional(profesional.nombre)"></span>
                        <span class="min-w-0 flex-1 space-y-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="break-words text-lg font-semibold text-gray-900" x-text="profesional.nombre"></span>
                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">Disponible</span>
                            </span>
                            <span class="block text-xs leading-5 text-gray-600">Primera consulta disponible para este horario</span>
                            <span x-show="kinesiologaId === profesional.kinesiologa_id" class="block text-xs font-semibold text-indigo-700">✓ Seleccionada</span>
                        </span>
                    </span>
                    <span class="flex shrink-0 flex-col gap-1 border-t border-indigo-100 pt-3 sm:border-l sm:border-t-0 sm:pl-5 sm:pt-0 sm:text-right">
                        <span class="text-xs font-medium uppercase text-gray-600">Horario estimado</span>
                        <span class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <span class="font-semibold text-gray-900" x-text="inicioSeleccionado ? horaLegible(inicioSeleccionado) + ' a ' + horaLegible(profesional.fin_at) : ''"></span>
                            <span class="rounded bg-indigo-100 px-2 py-1 text-xs text-gray-700" x-text="inicioSeleccionado ? duracionLegible(inicioSeleccionado, profesional.fin_at) : ''"></span>
                        </span>
                    </span>
                </button>
            </template>
        </fieldset>
    </div>

    <div id="profesional-error" x-ref="errorProfesional" tabindex="-1" x-show="errorProfesional || erroresServidor.kinesiologa_id" x-cloak role="alert" class="rounded-lg text-sm text-red-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <p x-show="errorProfesional" x-text="errorProfesional"></p>
        <p x-show="erroresServidor.kinesiologa_id" x-text="erroresServidor.kinesiologa_id"></p>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-indigo-50 p-4 text-sm" aria-live="polite">
        <p class="font-semibold text-gray-900">Selección actual:</p>
        <div class="flex min-w-0 flex-wrap gap-2">
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Fecha: <strong class="text-gray-900" x-text="fechaSeleccionada ? fechaLegible(fechaSeleccionada) : 'Sin seleccionar'"></strong></p>
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Inicio: <strong class="text-gray-900" x-text="inicioSeleccionado ? horaLegible(inicioSeleccionado) : 'Sin seleccionar'"></strong></p>
            <p class="min-w-0 break-words rounded-lg bg-white px-3 py-2 text-gray-600">Profesional: <strong class="text-indigo-700" x-text="profesionalSeleccionada ? profesionalSeleccionada.nombre : 'Sin seleccionar'"></strong></p>
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Fin: <strong class="text-gray-900" x-text="profesionalSeleccionada ? horaLegible(profesionalSeleccionada.fin_at) : 'Sin seleccionar'"></strong></p>
        </div>
    </div>
</section>
