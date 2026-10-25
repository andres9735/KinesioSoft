<section x-show="paso === 4" x-cloak aria-labelledby="titulo-paso-4" class="min-w-0 space-y-6">
    <div class="space-y-2">
        <h2 id="titulo-paso-4" x-ref="tituloPaso4" tabindex="-1" class="rounded-lg text-2xl font-semibold text-gray-900 focus:outline-none">Revisar y confirmar</h2>
        <p class="text-sm leading-6 text-gray-600">Verificá la información de tu turno. Si necesitás corregir algún dato, podés volver al paso correspondiente antes de confirmar.</p>
        <dl class="text-sm"><div class="flex flex-wrap gap-2"><dt class="text-gray-600">Tipo</dt><dd class="font-semibold text-indigo-700">Primera consulta</dd></div></dl>
    </div>

    <section aria-labelledby="revision-fecha-titulo" class="min-w-0 space-y-4 rounded-xl border border-gray-100 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="revision-fecha-titulo" class="font-semibold text-gray-900">Fecha y horario</h3>
            <button type="button" @click="editarDesdeRevision(1)" :disabled="bloqueado" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Cambiar fecha y horario</button>
        </div>
        <dl class="grid min-w-0 grid-cols-1 gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-lg bg-indigo-50 p-3"><dt class="text-xs text-gray-600">Fecha</dt><dd class="mt-1 text-gray-900" x-text="intervaloSeleccionado ? fechaLegible(intervaloSeleccionado.inicio_at.slice(0, 10)) : 'Pendiente de selección'">Pendiente de selección</dd></div>
            <div class="rounded-lg bg-indigo-50 p-3"><dt class="text-xs text-gray-600">Hora de inicio</dt><dd class="mt-1 text-gray-900" x-text="intervaloSeleccionado ? horaLegible(intervaloSeleccionado.inicio_at) : 'Pendiente de selección'"></dd></div>
            <div class="rounded-lg bg-indigo-50 p-3"><dt class="text-xs text-gray-600">Horario de atención</dt><dd class="mt-1 font-semibold text-gray-900" x-text="intervaloSeleccionado ? horaLegible(intervaloSeleccionado.inicio_at) + ' a ' + horaLegible(intervaloSeleccionado.fin_at) : 'Pendiente de selección'"></dd></div>
        </dl>
    </section>

    <section aria-labelledby="revision-profesional-titulo" class="min-w-0 space-y-4 rounded-xl border border-gray-100 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="revision-profesional-titulo" class="font-semibold text-gray-900">Profesional</h3>
            <button type="button" @click="editarDesdeRevision(2)" :disabled="bloqueado" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Cambiar profesional</button>
        </div>
        <dl><div class="flex min-w-0 items-center gap-3 rounded-xl bg-indigo-50 p-4">
            <dt class="sr-only">Profesional</dt>
            <dd class="flex min-w-0 items-center gap-3">
                <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 font-semibold text-white" x-text="profesionalSeleccionada ? inicialesProfesional(profesionalSeleccionada.nombre) : ''"></span>
                <span class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="min-w-0 max-w-full break-words font-semibold text-gray-900 [overflow-wrap:anywhere]" x-text="profesionalSeleccionada ? profesionalSeleccionada.nombre : 'Sin seleccionar'"></span>
                    <span x-show="profesionalSeleccionada" class="rounded-full bg-indigo-100 px-2 py-1 text-xs text-indigo-700">Seleccionada</span>
                </span>
            </dd>
        </div></dl>
    </section>

    <section aria-labelledby="revision-motivo-titulo" class="min-w-0 space-y-4 rounded-xl border border-gray-100 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="revision-motivo-titulo" class="font-semibold text-gray-900">Motivo de consulta</h3>
            <button type="button" @click="editarDesdeRevision(3)" :disabled="bloqueado" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Editar motivo y documentación</button>
        </div>
        <dl><div><dt class="sr-only">Motivo</dt><dd class="whitespace-pre-wrap break-words rounded-lg bg-indigo-50 p-4 text-sm text-gray-900 [overflow-wrap:anywhere]" x-text="motivo"></dd></div></dl>
    </section>

    <section aria-labelledby="revision-documentacion-titulo" class="min-w-0 space-y-4 rounded-xl border border-gray-100 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="revision-documentacion-titulo" class="font-semibold text-gray-900">Documentación adjunta</h3>
            <p class="rounded-full bg-indigo-100 px-3 py-1 text-xs text-gray-700"><span x-text="archivos.length"></span> archivos · <span x-text="tamanioLegible(totalBytes)"></span></p>
        </div>
        <dl><div><dt class="sr-only">Documentación adjunta</dt><dd>
            <p x-show="archivos.length === 0" class="text-sm text-gray-600">Sin documentación adjunta</p>
            <ul class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                <template x-for="entrada in archivos" :key="entrada.id">
                    <li class="min-w-0 space-y-3 rounded-xl bg-indigo-50 p-4">
                        <div class="min-w-0 space-y-1">
                            <p class="break-words text-sm font-semibold text-gray-900 [overflow-wrap:anywhere]" x-text="entrada.archivo.name"></p>
                            <p class="text-xs text-gray-600" x-text="tamanioLegible(entrada.archivo.size)"></p>
                        </div>
                        <div class="min-w-0 rounded-lg bg-white p-3 text-sm">
                            <p class="text-xs text-gray-600">Descripción del adjunto:</p>
                            <p class="whitespace-pre-wrap break-words text-gray-900 [overflow-wrap:anywhere]" x-text="entrada.descripcion"></p>
                        </div>
                    </li>
                </template>
            </ul>
        </dd></div></dl>
    </section>

    <dl class="rounded-xl bg-indigo-50 p-5 text-sm"><div class="space-y-2">
        <dt class="font-semibold text-gray-900">Estado al registrar</dt>
        <dd class="inline-block rounded-full bg-indigo-100 px-3 py-1 font-semibold text-gray-900">Pendiente</dd>
    </div></dl>
    <p class="text-sm leading-6 text-gray-600">Al confirmar, el turno queda registrado en estado Pendiente y ocupa agenda. Pendiente no significa que el turno esté esperando aprobación.</p>
    <p class="rounded-xl bg-indigo-50 p-4 text-xs leading-5 text-gray-600">La disponibilidad se volverá a validar al confirmar el turno. La fecha, horario y profesional seleccionados todavía no constituyen una reserva temporal.</p>
    <button type="button" @click="confirmarTurno()" :disabled="!puedeConfirmar" :aria-busy="enviando" x-text="enviando ? 'Registrando turno…' : 'Confirmar turno'" class="min-h-11 w-full rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 sm:w-auto">Confirmar turno</button>
</section>
