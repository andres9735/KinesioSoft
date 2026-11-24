<div data-resumen-fuentes="{{ $tipo }}" class="min-w-0" x-data="{ fuentes: null }" x-effect="fuentes = resumenFuentes('{{ $tipo }}')">
    <template x-if="fuentes">
        <details class="min-w-0 rounded-xl border border-indigo-100 bg-white p-3 text-sm">
            <summary class="min-h-11 cursor-pointer font-semibold text-indigo-800" aria-label="Fuentes de {{ $titulo }} — propuesta sin confirmar">
                Fuentes de información <span class="font-normal">— propuesta sin confirmar</span>
                <span class="mt-1 block text-xs font-normal text-slate-600" x-text="'Declaración: ' + fuentes.declaracion.items.length + ' · Previa: ' + fuentes.previa.items.length + (fuentes.previa.sustituida ? ' (sustituir)' : '') + ' · Aportes: ' + fuentes.complemento.cantidad"></span>
            </summary>
            <div class="mt-3 space-y-3 break-words">
                <p x-show="fuentes.requiereRevision" class="text-xs text-amber-900">Propuesta en revisión: comprobá los pendientes de esta categoría.</p>
                <section data-fuente-resumen="declaracion" class="space-y-1 rounded-lg bg-indigo-50 p-3">
                    <h4 class="font-semibold">Desde la declaración del paciente</h4>
                    <p class="text-xs" x-show="!fuentes.declaracion.general" x-text="fuentes.declaracion.respuesta === 'no' ? 'El paciente declaró No.' : fuentes.declaracion.respuesta === 'si' ? 'El paciente declaró Sí.' : 'Sin respuesta original Sí/No disponible.'"></p>
                    <p x-show="!fuentes.declaracion.items.length" x-text="['OBSERVAR', 'DESCARTAR'].includes(fuentes.declaracion.dictamen) ? 'Sin ítems propuestos: declaración observada o descartada.' : 'Sin ítems propuestos desde esta fuente.'"></p>
                    <ul class="space-y-2">
                        <template x-for="(item, indice) in fuentes.declaracion.items" :key="indice">
                            <li>
                                <p class="text-xs font-medium" x-text="(item.dictamen === 'CORREGIR' ? 'Corrección profesional propuesta' : 'Original propuesto para admisión') + (item.item_origen_id ? ' · Ítem original #' + item.item_origen_id : ' · Nuevo dato')"></p>
                                <p class="whitespace-pre-line" x-text="item.descripcion || 'Descripción pendiente' "></p>
                                <p class="text-xs text-slate-600" x-text="(item.procedencia === 'REFERIDO_PACIENTE' ? 'Referido por el paciente' : item.procedencia === 'RELEVAMIENTO_PROFESIONAL' ? 'Relevamiento profesional' : 'Procedencia pendiente') + ' · Pendiente de codificación'"></p>
                            </li>
                        </template>
                    </ul>
                </section>
                <section data-fuente-resumen="previa" class="space-y-1 rounded-lg bg-slate-50 p-3">
                    <h4 class="font-semibold">Información clínica previa</h4>
                    <p x-show="!fuentes.previa.existe">Sin versión clínica previa.</p>
                    <template x-if="fuentes.previa.existe">
                        <div class="space-y-1" :class="fuentes.previa.sustituida ? 'text-slate-500' : ''">
                            <p class="text-xs font-medium" x-text="fuentes.previa.sustituida ? 'Referencia histórica: sustitución seleccionada, fuera de la propuesta actual.' : fuentes.previa.items.length ? 'Registros previos propuestos para conservación.' : 'Referencia de la versión previa; no determina por sí sola el nuevo resultado.'"></p>
                            <p x-text="'Resultado previo: ' + ((@js(\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS))[fuentes.previa.resultado] || 'Sin resultado previo registrado')"></p>
                            <ul class="space-y-1">
                                <template x-for="(item, indice) in fuentes.previa.items" :key="indice">
                                    <li :class="fuentes.previa.sustituida ? 'line-through' : ''"><p class="whitespace-pre-line" x-text="item.descripcion"></p><p class="text-xs" x-text="(@js(\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS))[item.procedencia] || 'Procedencia no disponible'"></p></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </section>
                <section data-fuente-resumen="complemento" class="space-y-1 rounded-lg border border-indigo-100 p-3">
                    <h4 class="font-semibold">Aporte profesional complementario</h4>
                    <p x-text="(@js(\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS))[fuentes.complemento.resultado] || 'Sin aporte complementario seleccionado.'"></p>
                    <p x-show="fuentes.complemento.resultado === 'INFORMADO'" x-text="'Registros en los controles: ' + fuentes.complemento.cantidad + '. Editalos debajo.'"></p>
                </section>
            </div>
        </details>
    </template>
</div>
