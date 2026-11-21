        <form method="POST" action="{{ route('historia-clinica.revision.preview', ['turno' => $turno->id, 'declaracion' => $declaracion->id]) }}" class="space-y-6" x-data="revisionDeclaracion(@js($fuente->bloques), @js($entrada))">
            @csrf
            <input type="hidden" name="contexto" value="{{ $contexto }}">
            <input type="hidden" name="propuesta" :value="JSON.stringify(contrato())">
            <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <label class="block">Especialidad de la profesional
                    <select x-model="especialidad_id" required class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná tu especialidad</option>@foreach ($especialidades as $especialidad)<option value="{{ $especialidad->id }}">{{ $especialidad->nombre }}</option>@endforeach</select>
                </label>
                <label class="block">Procedencia del relevamiento complementario y familiar
                    <select x-model="procedencia_relevamiento" required class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select>
                </label>
            </div>
            @foreach (\App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS as $bloque => $titulo)
                @php($esLista = array_key_exists($bloque, \App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::LISTAS))
                <fieldset class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                    <legend class="px-2 text-lg font-semibold">{{ $titulo }}</legend>
                    <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="font-semibold">Declaración original del paciente — sin modificaciones</h3>
                        @if ($esLista)
                            <ul class="space-y-2">@forelse ($fuente->bloques[$bloque]['items'] as $item)<li class="whitespace-pre-line">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@empty<li>Sin ítems declarados. Esta lista vacía no acredita «No presenta».</li>@endforelse</ul>
                        @else
                            <p class="whitespace-pre-line">{{ $fuente->bloques[$bloque]['valor'] ?? 'Sin dato declarado' }}</p>
                        @endif
                    </div>
                    <label class="block">Dictamen profesional
                        <select x-model="dictamenes.{{ $bloque }}.dictamen" required class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná el dictamen</option>@foreach (['VALIDAR' => 'Validar', 'CORREGIR' => 'Corregir', 'OBSERVAR' => 'Observar', 'DESCARTAR' => 'Descartar'] as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select>
                    </label>
                    @if ($bloque === 'antecedentes')
                        <fieldset class="space-y-3" x-show="dictamenes.antecedentes.dictamen === 'VALIDAR'" :disabled="dictamenes.antecedentes.dictamen !== 'VALIDAR'">
                            <legend class="font-semibold">Clasificación profesional de cada antecedente</legend>
                            @foreach ($fuente->bloques[$bloque]['items'] as $item)
                                <label class="block">Ítem #{{ $item['item_origen_id'] }} — {{ $item['descripcion'] }}
                                    <select x-model="dictamenes.antecedentes.clasificacion[{{ $item['item_origen_id'] }}]" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Sin clasificar</option><option value="personales">Antecedente personal</option><option value="familiares">Antecedente familiar</option></select>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                    <fieldset class="space-y-4" x-show="dictamenes.{{ $bloque }}.dictamen === 'CORREGIR'" :disabled="dictamenes.{{ $bloque }}.dictamen !== 'CORREGIR'">
                        <legend class="font-semibold">Corrección profesional — conserva el original</legend>
                        @if ($esLista)
                            <template x-for="(item, index) in dictamenes.{{ $bloque }}.correccion.items" :key="index">
                                <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                                    <label class="block">Ítem original relacionado
                                        <select x-model="item.item_origen_id" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Nuevo aporte profesional</option>@foreach ($fuente->bloques[$bloque]['items'] as $item)<option value="{{ $item['item_origen_id'] }}">Ítem #{{ $item['item_origen_id'] }}</option>@endforeach</select>
                                    </label>
                                    <label class="block">Tipo de antecedente
                                        <select x-model="item.tipo_destino" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná el tipo</option>@foreach ($bloque === 'antecedentes' ? ['personales' => 'Antecedente personal', 'familiares' => 'Antecedente familiar'] : [$bloque => $titulo] as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select>
                                    </label>
                                    <label class="block">Descripción corregida<textarea x-model="item.descripcion" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label>
                                    <label class="block">Procedencia del dato corregido<select x-model="item.procedencia" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></label>
                                    <button type="button" @click="dictamenes.{{ $bloque }}.correccion.items.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar del resultado corregido</button>
                                </div>
                            </template>
                            <button type="button" @click="agregarCorreccion('{{ $bloque }}')" class="min-h-11 font-semibold text-indigo-700 underline">Agregar dato corregido</button>
                            <p class="text-sm">Los antecedentes admitidos quedan Pendientes de codificación.</p>
                        @elseif ($bloque === 'nivel_actividad_fisica')
                            <label class="block">Actividad física corregida<select x-model="dictamenes.{{ $bloque }}.correccion.valor" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Sin informar</option><option value="SEDENTARIO">Sedentario</option><option value="MODERADO">Moderado</option><option value="ACTIVO">Activo</option></select></label>
                        @elseif (in_array($bloque, ['peso_kg', 'altura_cm'], true))
                            <label class="block">Valor corregido (hasta dos decimales)<input type="number" step="0.01" min="{{ $bloque === 'peso_kg' ? 1 : 30 }}" max="{{ $bloque === 'peso_kg' ? 500 : 250 }}" x-model="dictamenes.{{ $bloque }}.correccion.valor" class="mt-1 w-full rounded-lg border-gray-300"></label>
                        @else
                            <label class="block">Valor corregido<textarea x-model="dictamenes.{{ $bloque }}.correccion.valor" maxlength="{{ $bloque === 'ocupacion' ? 255 : 5000 }}" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label>
                        @endif
                    </fieldset>
                    <label class="block">Justificación clínica (obligatoria al observar o descartar)
                        <textarea x-model="dictamenes.{{ $bloque }}.justificacion" :required="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300"></textarea>
                    </label>
                    <p x-show="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" class="text-sm text-amber-900">El dato declarado quedará excluido de la futura versión. Si afecta un bloque obligatorio, completá su relevamiento profesional.</p>
                </fieldset>
            @endforeach
            @include('historia-clinica.revision-complementos')
            <div class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
                <p>La vista previa no confirma la revisión, no abre una historia ni cambia el turno.</p>
                <noscript><p role="alert">Activá JavaScript para preparar los dictámenes y la vista previa.</p></noscript>
                <button type="submit" class="min-h-11 rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Ver vista previa de incorporación</button>
            </div>
        </form>
