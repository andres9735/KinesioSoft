<section x-show="paso === 3" x-cloak aria-labelledby="titulo-paso-3" class="min-w-0 space-y-6">
    <div class="space-y-2">
        <h2 id="titulo-paso-3" x-ref="tituloPaso3" tabindex="-1" class="rounded-lg text-2xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Motivo y documentación</h2>
        <p class="text-sm leading-6 text-gray-600">Ingresá el motivo de la consulta. También podés adjuntar documentación clínica si ayuda a contextualizar tu primera consulta.</p>
    </div>
    <div class="flex min-w-0 flex-col items-stretch gap-4 rounded-xl bg-indigo-50 p-4 text-sm sm:flex-row sm:flex-wrap sm:items-center" aria-label="Turno elegido">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-medium uppercase text-gray-600">Fecha seleccionada</p>
            <p class="mt-1 font-semibold text-gray-900" x-text="fechaSeleccionada ? fechaLegible(fechaSeleccionada) : 'Sin seleccionar'"></p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase text-gray-600">Hora de inicio</p>
            <p class="mt-1 font-semibold text-gray-900" x-text="inicioSeleccionado ? horaLegible(inicioSeleccionado) : 'Sin seleccionar'"></p>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-medium uppercase text-gray-600">Profesional</p>
            <p class="mt-1 break-words font-semibold text-gray-900 [overflow-wrap:anywhere]" x-text="profesionalSeleccionada ? profesionalSeleccionada.nombre : 'Sin seleccionar'"></p>
        </div>
        <div>
            <p class="text-xs font-medium uppercase text-gray-600">Horario estimado</p>
            <p class="mt-1 font-semibold text-gray-900" x-text="intervaloSeleccionado ? horaLegible(intervaloSeleccionado.inicio_at) + ' a ' + horaLegible(intervaloSeleccionado.fin_at) + ' (' + duracionLegible(intervaloSeleccionado.inicio_at, intervaloSeleccionado.fin_at) + ')' : 'Sin seleccionar'"></p>
        </div>
        <button type="button" @click="anterior()" :disabled="bloqueado" class="min-h-11 rounded-lg px-3 py-2 font-semibold text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Cambiar profesional</button>
    </div>

    <div class="space-y-2">
        <x-input-label for="motivo-solicitud" value="Motivo de la consulta (obligatorio)" />
        <p id="motivo-ayuda" class="text-sm text-gray-500">Describí brevemente el motivo por el cual solicitás la atención.</p>
        <textarea id="motivo-solicitud" name="motivo_solicitud" x-ref="motivo" x-model="motivo" @input="corregirMotivo()" required maxlength="{{ $configuracionWizard['maxMotivo'] }}" rows="5" aria-describedby="motivo-ayuda motivo-contador motivo-error motivo-error-servidor" :aria-invalid="errorMotivo !== '' || !!erroresServidor.motivo_solicitud" class="block w-full min-w-0 rounded-lg border-gray-300 bg-indigo-50 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        <p id="motivo-contador" class="text-right text-xs text-gray-500"><span x-text="longitudMotivo">0</span> / {{ $configuracionWizard['maxMotivo'] }} caracteres</p>
        <p id="motivo-error" x-show="errorMotivo" x-cloak x-text="errorMotivo" class="text-sm text-red-700" role="alert"></p>
        <p id="motivo-error-servidor" x-show="erroresServidor.motivo_solicitud" x-cloak x-text="erroresServidor.motivo_solicitud" class="text-sm text-red-700" role="alert"></p>
    </div>

    <div class="min-w-0 space-y-4">
        <h3 id="documentacion-titulo" class="text-base font-semibold text-gray-900">Estudios o documentación previa <span class="font-normal text-gray-500">(opcional)</span></h3>
        <p id="archivos-ayuda" class="text-sm leading-6 text-gray-600">
            Formatos permitidos: {{ implode('/', array_map('strtoupper', $configuracionWizard['limites']['extensionesPermitidas'])) }}.
            Hasta {{ $configuracionWizard['limites']['maxArchivos'] }} archivos.
            Máximo {{ $configuracionWizard['limites']['maxBytesPorArchivo'] / (1024 * 1024) }} MiB por archivo y {{ $configuracionWizard['limites']['maxBytesTotal'] / (1024 * 1024) }} MiB en total.
        </p>
        <div @dragover.prevent @drop.prevent="agregarArchivos({ target: { files: $event.dataTransfer.files, value: '' } })" class="rounded-xl border border-dashed border-indigo-200 bg-indigo-50 p-5 text-center sm:p-7">
            <p class="text-sm font-medium text-gray-700">Agregar archivos</p>
            <div class="relative mt-3">
                <input id="adjuntos" type="file" multiple accept="{{ implode(',', array_map(static fn ($extension) => '.'.$extension, $configuracionWizard['limites']['extensionesPermitidas'])) }}" @change="agregarArchivos($event)" aria-describedby="archivos-seleccion archivos-ayuda archivos-errores archivos-privacidad" :aria-invalid="erroresArchivos.length > 0 || erroresGeneralesArchivos.length > 0 || !!erroresServidor.adjuntos" class="peer sr-only focus:ring-2 focus:ring-indigo-500" />
                <label for="adjuntos" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-lg bg-white px-4 py-3 text-sm font-semibold text-indigo-700 peer-focus:ring-2 peer-focus:ring-indigo-500 peer-focus:ring-offset-2">Elegir archivos</label>
            </div>
            <p id="archivos-seleccion" aria-live="polite" class="mt-3 text-sm text-gray-700" x-text="archivos.length > 0 ? 'Agregar más archivos' : 'Ningún archivo seleccionado'">Ningún archivo seleccionado</p>
            <p class="mt-2 text-xs text-gray-600" x-text="archivos.length > 0 ? 'Podés sumar más estudios o documentación si lo necesitás.' : 'Elegí archivos o arrastralos aquí.'">Elegí archivos o arrastralos aquí.</p>
        </div>
        <div id="archivos-errores" x-ref="documentacion" tabindex="-1" class="space-y-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-live="polite">
            <p x-show="erroresServidor.adjuntos" x-cloak x-text="erroresServidor.adjuntos" class="text-sm text-red-700"></p>
            <template x-for="error in erroresGeneralesArchivos" :key="error">
                <p class="text-sm text-red-700" x-text="error"></p>
            </template>
            <p x-show="erroresArchivos.length > 0" x-cloak class="text-sm text-red-700">Quitá o reemplazá los archivos indicados para continuar.</p>
        </div>
        <ul aria-labelledby="documentacion-titulo" class="space-y-3">
            <template x-for="entrada in archivos" :key="entrada.id">
                <li class="min-w-0 space-y-3 rounded-xl border bg-indigo-50 p-4" :class="(entrada.descripcionRevisada && errorDescripcion(entrada)) || entrada.errorDescripcionServidor || entrada.error || entrada.errorServidor ? 'border-red-300' : 'border-transparent'">
                    <div class="flex min-w-0 flex-col items-start justify-between gap-3 sm:flex-row">
                        <div class="min-w-0 flex-1 space-y-1">
                            <p :id="'archivo-nombre-' + entrada.id" class="break-words text-sm font-semibold text-gray-900 [overflow-wrap:anywhere]" x-text="entrada.archivo.name"></p>
                            <p class="text-xs text-gray-500" x-text="tamanioLegible(entrada.archivo.size)"></p>
                            <p class="text-xs" :class="entrada.error || entrada.errorServidor ? 'text-red-700' : 'text-gray-600'" :id="'archivo-estado-' + entrada.id" :role="entrada.error || entrada.errorServidor ? 'alert' : null" :aria-labelledby="'archivo-nombre-' + entrada.id" x-text="entrada.error || entrada.errorServidor || 'Seleccionado en esta página'"></p>
                        </div>
                        <button type="button" @click="quitarArchivo(entrada.id)" :aria-label="'Quitar ' + entrada.archivo.name" :aria-describedby="'archivo-estado-' + entrada.id" class="min-h-11 shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Quitar</button>
                    </div>
                    <div class="min-w-0 space-y-2">
                        <label :for="'descripcion-adjunto-' + entrada.id" class="block text-sm font-medium text-gray-900">Descripción del adjunto <span aria-hidden="true" class="text-red-600">*</span><span class="sr-only" x-text="' de ' + entrada.archivo.name"></span></label>
                        <input type="text" :id="'descripcion-adjunto-' + entrada.id" :value="entrada.descripcion" @input="corregirDescripcion(entrada.id, $event.target.value)" @blur="revisarDescripcion(entrada.id)" required maxlength="500" :aria-describedby="'descripcion-ayuda-' + entrada.id + ' descripcion-error-' + entrada.id + ' descripcion-error-servidor-' + entrada.id" :aria-invalid="!!errorDescripcion(entrada) || !!entrada.errorDescripcionServidor" class="block min-h-11 w-full min-w-0 rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="(entrada.descripcionRevisada && errorDescripcion(entrada)) || entrada.errorDescripcionServidor ? 'border-red-500' : ''" placeholder="Ej: Pedido médico para kinesiología lumbar" />
                        <p :id="'descripcion-ayuda-' + entrada.id" class="text-xs text-gray-500">Obligatoria para este archivo. Entre 1 y 500 caracteres significativos.</p>
                        <p :id="'descripcion-error-' + entrada.id" x-show="entrada.descripcionRevisada && errorDescripcion(entrada)" x-cloak x-text="errorDescripcion(entrada)" class="text-xs text-red-700" role="alert"></p>
                        <p :id="'descripcion-error-servidor-' + entrada.id" x-show="entrada.errorDescripcionServidor" x-cloak x-text="entrada.errorDescripcionServidor" class="text-xs text-red-700" role="alert"></p>
                    </div>
                    <div class="min-w-0 space-y-2 border-t border-indigo-100 pt-3">
                        <label :for="'reemplazo-adjunto-' + entrada.id" class="block text-xs font-medium text-gray-700">Reemplazar<span class="sr-only" x-text="' ' + entrada.archivo.name"></span></label>
                        <input type="file" :id="'reemplazo-adjunto-' + entrada.id" accept="{{ implode(',', array_map(static fn ($extension) => '.'.$extension, $configuracionWizard['limites']['extensionesPermitidas'])) }}" @change="if ($event.target.files.length) reemplazarArchivo(entrada.id, $event.target.files[0]); $event.target.value = ''" :aria-describedby="'archivo-estado-' + entrada.id" class="block min-h-11 w-full min-w-0 rounded-lg text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:font-semibold file:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" />
                    </div>
                </li>
            </template>
        </ul>
        <ul x-show="erroresSeleccionArchivos.length" x-cloak aria-label="Archivos rechazados" aria-live="polite" class="space-y-3">
            <template x-for="rechazo in erroresSeleccionArchivos" :key="rechazo.id">
                <li class="min-w-0 space-y-2 rounded-xl border border-red-300 bg-red-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="min-w-0 flex-1 break-words text-sm font-semibold text-gray-900 [overflow-wrap:anywhere]"><span x-text="rechazo.nombre"></span> <span class="rounded bg-red-100 px-2 py-1 text-xs font-medium text-red-700">No permitido</span></p>
                        <button type="button" @click="descartarErrorSeleccion(rechazo.id)" :aria-label="'Descartar rechazo de ' + rechazo.nombre" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">Descartar</button>
                    </div>
                    <p role="alert" class="rounded-lg bg-white p-2 text-sm text-red-700" x-text="rechazo.error"></p>
                    <p class="text-xs text-gray-600">El archivo no pudo agregarse. Revisá el formato y los límites permitidos y volvé a intentarlo.</p>
                </li>
            </template>
        </ul>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="rounded-full bg-indigo-50 px-3 py-2 text-xs text-gray-700"><span x-text="archivos.length">0</span> archivos seleccionados · <span x-text="tamanioLegible(totalBytes)">0 MiB</span></p>
            <p id="archivos-privacidad" class="text-xs leading-5 text-gray-500">Los archivos se enviarán al confirmar el turno. Su contenido se verificará en ese momento.</p>
        </div>
    </div>
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-xl bg-indigo-50 p-4 text-sm">
        <p class="font-semibold text-indigo-700">Selección actual:</p>
        <div class="flex min-w-0 flex-wrap gap-2">
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Fecha: <strong class="text-gray-900" x-text="fechaSeleccionada ? fechaLegible(fechaSeleccionada) : 'Sin seleccionar'"></strong></p>
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Inicio: <strong class="text-gray-900" x-text="inicioSeleccionado ? horaLegible(inicioSeleccionado) : 'Sin seleccionar'"></strong></p>
            <p class="min-w-0 break-words rounded-lg bg-white px-3 py-2 text-gray-600 [overflow-wrap:anywhere]">Profesional: <strong class="text-indigo-700" x-text="profesionalSeleccionada ? profesionalSeleccionada.nombre : 'Sin seleccionar'"></strong></p>
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Adjuntos: <strong class="text-gray-900" x-text="archivos.length"></strong></p>
        </div>
    </div>
</section>
