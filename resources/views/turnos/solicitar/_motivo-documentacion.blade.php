<section x-show="paso === 1" aria-labelledby="titulo-paso-1" class="space-y-6">
    <h2 id="titulo-paso-1" x-ref="tituloPaso1" tabindex="-1" class="rounded-lg text-xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Motivo y documentación</h2>
    <div class="space-y-2">
        <x-input-label for="motivo-solicitud" value="Motivo de la consulta (obligatorio)" />
        <p id="motivo-ayuda" class="text-sm text-gray-500">Describí brevemente por qué necesitás atención kinésica.</p>
        <textarea id="motivo-solicitud" name="motivo_solicitud" x-ref="motivo" x-model="motivo" @input="corregirMotivo()" required maxlength="{{ $configuracionWizard['maxMotivo'] }}" rows="5" aria-describedby="motivo-ayuda motivo-contador motivo-error motivo-error-servidor" :aria-invalid="errorMotivo !== '' || !!erroresServidor.motivo_solicitud" class="block w-full rounded-lg border-gray-300 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        <p id="motivo-contador" class="text-right text-xs text-gray-500"><span x-text="longitudMotivo">0</span> / {{ $configuracionWizard['maxMotivo'] }} caracteres</p>
        <p id="motivo-error" x-show="errorMotivo" x-cloak x-text="errorMotivo" class="text-sm text-red-700" role="alert"></p>
        <p id="motivo-error-servidor" x-show="erroresServidor.motivo_solicitud" x-cloak x-text="erroresServidor.motivo_solicitud" class="text-sm text-red-700" role="alert"></p>
    </div>

    <div class="space-y-4 border-t border-gray-100 pt-6">
        <h3 id="documentacion-titulo" class="text-base font-semibold text-gray-900">Estudios o documentación previa <span class="font-normal text-gray-500">(opcional)</span></h3>
        <p id="archivos-ayuda" class="text-sm leading-6 text-gray-600">
            Formatos permitidos: {{ implode('/', array_map('strtoupper', $configuracionWizard['limites']['extensionesPermitidas'])) }}.
            Hasta {{ $configuracionWizard['limites']['maxArchivos'] }} archivos.
            Máximo {{ $configuracionWizard['limites']['maxBytesPorArchivo'] / (1024 * 1024) }} MiB por archivo y {{ $configuracionWizard['limites']['maxBytesTotal'] / (1024 * 1024) }} MiB en total.
        </p>
        <div class="rounded-xl border border-dashed border-indigo-200 bg-indigo-50 p-5">
            <x-input-label for="adjuntos" value="Agregar archivos" />
            <input id="adjuntos" type="file" multiple accept="{{ implode(',', array_map(static fn ($extension) => '.'.$extension, $configuracionWizard['limites']['extensionesPermitidas'])) }}" @change="agregarArchivos($event)" aria-describedby="archivos-ayuda archivos-errores archivos-privacidad" :aria-invalid="erroresArchivos.length > 0 || erroresGeneralesArchivos.length > 0 || !!erroresServidor.adjuntos" class="mt-3 block w-full min-w-0 rounded-lg text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-4 file:py-3 file:font-semibold file:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" />
        </div>
        <div id="archivos-errores" x-ref="documentacion" tabindex="-1" class="space-y-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-live="polite">
            <p x-show="erroresServidor.adjuntos" x-cloak x-text="erroresServidor.adjuntos" class="text-sm text-red-700"></p>
            <template x-for="error in erroresGeneralesArchivos" :key="error">
                <p class="text-sm text-red-700" x-text="error"></p>
            </template>
            <p x-show="erroresArchivos.length > 0" x-cloak class="text-sm text-red-700">Quitá o reemplazá los archivos indicados para continuar.</p>
        </div>
        <p class="text-sm text-gray-600"><span x-text="archivos.length">0</span> archivos seleccionados · <span x-text="tamanioLegible(totalBytes)">0 MiB</span></p>
        <ul aria-labelledby="documentacion-titulo" class="space-y-3">
            <template x-for="entrada in archivos" :key="entrada.id">
                <li class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <p :id="'archivo-nombre-' + entrada.id" class="break-words text-sm font-semibold text-gray-900" x-text="entrada.archivo.name"></p>
                        <p class="text-xs text-gray-500" x-text="tamanioLegible(entrada.archivo.size)"></p>
                        <p class="text-xs" :class="entrada.error || entrada.errorServidor ? 'text-red-700' : 'text-gray-600'" :id="'archivo-estado-' + entrada.id" :role="entrada.error || entrada.errorServidor ? 'alert' : null" :aria-labelledby="'archivo-nombre-' + entrada.id" x-text="entrada.error || entrada.errorServidor || 'Seleccionado en esta página'"></p>
                    </div>
                    <button type="button" @click="quitarArchivo(entrada.id)" :aria-label="'Quitar ' + entrada.archivo.name" :aria-describedby="'archivo-estado-' + entrada.id" class="min-h-11 shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Quitar</button>
                </li>
            </template>
        </ul>
        <p id="archivos-privacidad" class="text-xs leading-5 text-gray-500">Los archivos se enviarán al confirmar el turno. Su contenido se verificará en ese momento.</p>
    </div>
</section>
