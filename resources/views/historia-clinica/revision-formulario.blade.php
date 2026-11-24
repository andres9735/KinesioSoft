@php($baseUX = $versionBase ? [
        'campos' => $versionBase->only(\App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::CAMPOS),
        'bloques' => collect(\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS)->mapWithKeys(fn ($titulo, $tipo) => [$tipo => [
            'resultado' => $versionBase->relevamientos[$tipo] ?? 'NO_RELEVADO',
            'items' => $versionBase->items->where('tipo', $tipo)->map(fn ($item) => $item->only(['descripcion', 'procedencia', 'estado_codificacion']))->values()->all(),
        ]])->all(),
        'familia' => ['resultado' => $versionBase->resultado_nucleo_familiar],
    ] : null)
@if (!($redisenioRevision ?? false))
        <form method="POST" action="{{ route('historia-clinica.revision.preview', ['turno' => $turno->id, 'declaracion' => $declaracion->id]) }}" class="space-y-6" x-data="revisionDeclaracion(@js($fuente->bloques), @js($entrada), @js(['base' => $baseUX]))">
            @csrf
            <input type="hidden" name="contexto" value="{{ $contexto }}">
            <input type="hidden" name="propuesta" :value="JSON.stringify(contrato())">
            <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-600" data-guia-revision>Revisá lo declarado por el paciente y completá el relevamiento profesional que aún falte, considerando los datos admitidos y la versión clínica previa. Validar un «No» conserva esa declaración; no registra por sí mismo «No presenta».</p>
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
                            @php($respuestaOriginal = $fuente->bloques[$bloque]['respuesta'] ?? null)
                            <p>{{ $respuestaOriginal === 'no' ? 'El paciente declaró No' : ($respuestaOriginal === 'si' ? 'El paciente declaró Sí' : 'Sin respuesta original disponible') }}</p>
                            @if ($fuente->bloques[$bloque]['items'] !== [] || $respuestaOriginal !== 'no')
                                <ul class="space-y-2">@forelse ($fuente->bloques[$bloque]['items'] as $item)<li class="whitespace-pre-line">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@empty<li>Sin ítems declarados. Esta lista vacía no acredita «No presenta».</li>@endforelse</ul>
                            @endif
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
                    <p x-show="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" class="text-sm text-amber-900">Este dato declarado no se incorporará a la futura versión.</p>
                </fieldset>
            @endforeach
            @include('historia-clinica.revision-complementos')
            <div class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
                <p>La vista previa no confirma la revisión, no abre una historia ni cambia el turno.</p>
                <noscript><p role="alert">Activá JavaScript para preparar los dictámenes y la vista previa.</p></noscript>
                <button type="submit" class="min-h-11 rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Ver vista previa de incorporación</button>
            </div>
        </form>

@else
@php($contextoUX = ['redisenio' => true, 'base' => $baseUX, 'especialidades' => $especialidades->pluck('id')->all(), 'errores' => $erroresRevision->messages()])
<form id="revision-formulario" method="POST" action="{{ route('historia-clinica.revision.preview', ['turno' => $turno->id, 'declaracion' => $declaracion->id]) }}"
    x-data="revisionDeclaracion(@js($fuente->bloques), @js($entrada), @js($contextoUX))"
    @invalid.capture="registrarInvalido($event)" @input="actualizarErrores($event)" @change="actualizarErrores($event)" @submit="intentoVistaPrevia = true"
    class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]">
    @csrf
    <input type="hidden" name="contexto" value="{{ $contexto }}">
    <input type="hidden" name="propuesta" :value="JSON.stringify(contrato())">
    <div class="min-w-0 space-y-4">
        <div class="space-y-4 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0"><dt class="text-slate-600">Paciente</dt><dd class="break-words text-lg font-semibold text-slate-900">{{ trim($paciente->user->name.' '.$paciente->user->apellido) }}</dd><dd>{{ $historia?->nro_historia_clinica ?? 'Sin historia clínica' }}</dd></div>
                <div><dt class="text-slate-600">Turno</dt><dd class="font-semibold">{{ $turno->inicio_at->format('d/m/Y') }}</dd><dd>{{ $turno->inicio_at->format('H:i') }} – {{ $turno->fin_at->format('H:i') }} · {{ config('app.timezone') }}</dd></div>
                <div><dt class="text-slate-600">Declaración</dt><dd class="font-semibold">#{{ $declaracion->id }}</dd><dd class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">{{ $declaracion->estado === \App\Models\DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION ? 'Pendiente de revisión' : $declaracion->estado }}</dd></div>
                <div class="min-w-0"><dt class="text-slate-600">Profesional</dt><dd class="break-words font-semibold">{{ trim($turno->kinesiologa->user->name.' '.$turno->kinesiologa->user->apellido) }}</dd></div>
            </dl>
            <p class="text-sm text-slate-600">La declaración se revisa antes de incorporar los datos clínicos al expediente. Salir no guarda cambios. Origen de la futura versión: <strong>Revisión de declaración de salud</strong>.</p>
            <p class="text-sm text-slate-600" data-guia-revision>En la Sección 2 revisás lo declarado por el paciente. En la Sección 3 completás el relevamiento profesional que aún falte, considerando los datos admitidos y la versión clínica previa. Validar un «No» conserva esa declaración; no registra por sí mismo «No presenta».</p>
            <div class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                <label class="block text-sm font-medium">Especialidad de la profesional<select x-model="especialidad_id" required class="mt-1 w-full rounded-lg border-gray-300" data-campo="especialidad_id" :id="idCampo('especialidad_id')" :aria-invalid="mensajes('especialidad_id').length ? 'true' : 'false'" :aria-describedby="mensajes('especialidad_id').length ? idCampo('especialidad_id') + '-error' : null"><option value="">Seleccioná tu especialidad</option>@foreach ($especialidades as $especialidad)<option value="{{ $especialidad->id }}">{{ $especialidad->nombre }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('especialidad_id') + '-error'" x-show="mensajes('especialidad_id').length" x-text="mensajes('especialidad_id').join(' ')"></span></label>
                <label class="block text-sm font-medium">Procedencia del relevamiento complementario y familiar<select x-model="procedencia_relevamiento" required class="mt-1 w-full rounded-lg border-gray-300" data-campo="procedencia_relevamiento" :id="idCampo('procedencia_relevamiento')" :aria-invalid="mensajes('procedencia_relevamiento').length ? 'true' : 'false'" :aria-describedby="mensajes('procedencia_relevamiento').length ? idCampo('procedencia_relevamiento') + '-error' : null"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('procedencia_relevamiento') + '-error'" x-show="mensajes('procedencia_relevamiento').length" x-text="mensajes('procedencia_relevamiento').join(' ')"></span></label>
            </div>
        </div>
        @if ($versionBase)
            <details class="rounded-xl border border-indigo-100 bg-white p-4 text-sm"><summary class="cursor-pointer font-semibold text-indigo-700">Consultar versión clínica previa que se conservará</summary>@include('historia-clinica.version-base', ['version' => $versionBase])</details>
        @endif
        <div id="revision-errores-generales" tabindex="-1" x-cloak x-show="Object.keys(erroresServidor).length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
            <h2 class="font-semibold">Revisá el resultado profesional</h2>
            <ul class="mt-2 space-y-2"><template x-for="(mensajesError, clave) in erroresServidor" :key="clave"><li><button type="button" @click="localizarError(clave) ? navegar(localizarError(clave).seccion, localizarError(clave).campo) : $el.closest('[role=alert]').focus()" class="min-h-11 text-left underline" x-text="mensajesError.join(' ')"></button></li></template></ul>
            <p x-cloak x-show="progreso().ambiguos.length">Hay errores generales sin ubicación inequívoca. Revisá el mensaje; no se asignan a un campo supuesto.</p>
        </div>
<section id="revision-seccion-salud" data-seccion="salud" class="scroll-mt-6 min-w-0 rounded-2xl border border-indigo-100 bg-white p-2 shadow-sm">
    <h2><button type="button" @click="alternar('salud')" :aria-expanded="abiertas.salud" aria-controls="revision-panel-salud" id="revision-titulo-salud" class="flex min-h-12 w-full flex-wrap items-center gap-3 rounded-xl bg-indigo-50 px-3 py-2 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <span aria-hidden="true" x-text="abiertas.salud ? '−' : '+'" class="text-xl text-indigo-700"></span><span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">1</span>
        <span class="min-w-0 flex-1 font-semibold text-slate-900">Datos de salud y características personales</span>
        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="progreso().secciones[0].estado === 'Completa' ? 'bg-green-100 text-green-900' : progreso().secciones[0].estado === 'Requiere atención' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900'" x-text="progreso().secciones[0].estado"></span>
    </button></h2>
    <div id="revision-panel-salud" role="region" aria-labelledby="revision-titulo-salud" x-show="abiertas.salud" style="display:none" class="min-w-0 space-y-4 p-3">
<div data-tarjetas-revision class="grid min-w-0 gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 28rem), 1fr))">@foreach (array_intersect_key(\App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS, array_flip(['objetivo_salud', 'peso_kg', 'altura_cm', 'ocupacion', 'nivel_actividad_fisica', 'detalle_actividad_fisica'])) as $bloque => $titulo)
                @php($esLista = array_key_exists($bloque, \App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::LISTAS))
                <fieldset class="min-w-0 space-y-3 rounded-xl border border-indigo-100 bg-white p-4">
                    <legend class="px-2 font-semibold text-slate-900">{{ $titulo }}</legend><div data-comparacion-declaracion class="grid min-w-0 gap-4" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr))">
                    <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-xs font-medium text-slate-600">Declarado por el paciente</h3>
                        @if ($esLista)
                            @php($respuestaOriginal = $fuente->bloques[$bloque]['respuesta'] ?? null)
                            <p>{{ $respuestaOriginal === 'no' ? 'El paciente declaró No' : ($respuestaOriginal === 'si' ? 'El paciente declaró Sí' : 'Sin respuesta original disponible') }}</p>
                            @if ($fuente->bloques[$bloque]['items'] !== [] || $respuestaOriginal !== 'no')
                                <ul class="space-y-2">@forelse ($fuente->bloques[$bloque]['items'] as $item)<li class="whitespace-pre-line">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@empty<li>Sin información declarada. No acredita «No presenta».</li>@endforelse</ul>
                            @endif
                        @else
                            <p class="whitespace-pre-line">{{ $fuente->bloques[$bloque]['valor'] ?? 'Sin información declarada' }}</p>
                        @endif
                    </div>
<div class="min-w-0 space-y-3"><label class="block text-sm font-medium">Dictamen profesional
                        <select x-model="dictamenes.{{ $bloque }}.dictamen" required class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.dictamen" :id="idCampo('dictamenes.{{ $bloque }}.dictamen')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.dictamen').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.dictamen').length ? idCampo('dictamenes.{{ $bloque }}.dictamen') + '-error' : null"><option value="">Elegí un dictamen</option>@foreach (['VALIDAR' => 'Validar', 'CORREGIR' => 'Corregir', 'OBSERVAR' => 'Observar', 'DESCARTAR' => 'Descartar'] as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.dictamen') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.dictamen').length" x-text="mensajes('dictamenes.{{ $bloque }}.dictamen').join(' ')"></span>
                    </label>
                    @if ($bloque === 'antecedentes')
                        <fieldset class="space-y-3" x-show="dictamenes.antecedentes.dictamen === 'VALIDAR'" :disabled="dictamenes.antecedentes.dictamen !== 'VALIDAR'">
                            <legend class="font-semibold">Clasificación profesional de cada antecedente</legend><p class="text-sm text-red-800" role="alert" x-show="mensajes('dictamenes.antecedentes.clasificacion').length" x-text="mensajes('dictamenes.antecedentes.clasificacion').join(' ')"></p>
                            @foreach ($fuente->bloques[$bloque]['items'] as $item)
                                <label class="block">Ítem #{{ $item['item_origen_id'] }} — {{ $item['descripcion'] }}
                                    <select x-model="dictamenes.antecedentes.clasificacion[{{ $item['item_origen_id'] }}]" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}" :id="idCampo('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}')" :aria-invalid="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').length ? idCampo('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}') + '-error' : null"><option value="">Sin clasificar</option><option value="personales">Antecedente personal</option><option value="familiares">Antecedente familiar</option></select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}') + '-error'" x-show="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').length" x-text="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').join(' ')"></span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                    <fieldset class="space-y-4" x-show="dictamenes.{{ $bloque }}.dictamen === 'CORREGIR'" :disabled="dictamenes.{{ $bloque }}.dictamen !== 'CORREGIR'">
                        <legend class="font-semibold">Corrección profesional — conserva el original</legend><p class="text-sm text-red-800" role="alert" x-show="mensajes('dictamenes.{{ $bloque }}.correccion').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion').join(' ')"></p>
                        @if ($esLista)
                            <template x-for="(item, index) in dictamenes.{{ $bloque }}.correccion.items" :key="index">
                                <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                                    <label class="block">Ítem original relacionado
                                        <select x-model="item.item_origen_id" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id') + '-error' : null"><option value="">Nuevo aporte profesional</option>@foreach ($fuente->bloques[$bloque]['items'] as $item)<option value="{{ $item['item_origen_id'] }}">Ítem #{{ $item['item_origen_id'] }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').join(' ')"></span>
                                    </label>
                                    <label class="block">Tipo de antecedente
                                        <select x-model="item.tipo_destino" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino') + '-error' : null"><option value="">Seleccioná el tipo</option>@foreach ($bloque === 'antecedentes' ? ['personales' => 'Antecedente personal', 'familiares' => 'Antecedente familiar'] : [$bloque => $titulo] as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').join(' ')"></span>
                                    </label>
                                    <label class="block">Descripción corregida<textarea x-model="item.descripcion" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').join(' ')"></span></label>
                                    <label class="block">Procedencia del dato corregido<select x-model="item.procedencia" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia') + '-error' : null"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').join(' ')"></span></label>
                                    <button type="button" @click="dictamenes.{{ $bloque }}.correccion.items.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar del resultado corregido</button>
                                </div>
                            </template>
                            <button type="button" @click="agregarCorreccion('{{ $bloque }}')" class="min-h-11 font-semibold text-indigo-700 underline">Agregar dato corregido</button>
                            <p class="text-sm">Los antecedentes admitidos quedan Pendientes de codificación.</p>
                        @elseif ($bloque === 'nivel_actividad_fisica')
                            <label class="block">Actividad física corregida<select x-model="dictamenes.{{ $bloque }}.correccion.valor" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.correccion.valor" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error' : null"><option value="">Sin informar</option><option value="SEDENTARIO">Sedentario</option><option value="MODERADO">Moderado</option><option value="ACTIVO">Activo</option></select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.valor').join(' ')"></span></label>
                        @elseif (in_array($bloque, ['peso_kg', 'altura_cm'], true))
                            <label class="block">Valor corregido (hasta dos decimales)<input type="number" step="0.01" min="{{ $bloque === 'peso_kg' ? 1 : 30 }}" max="{{ $bloque === 'peso_kg' ? 500 : 250 }}" x-model="dictamenes.{{ $bloque }}.correccion.valor" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.correccion.valor" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.valor').join(' ')"></span></label>
                        @else
                            <label class="block">Valor corregido<textarea x-model="dictamenes.{{ $bloque }}.correccion.valor" maxlength="{{ $bloque === 'ocupacion' ? 255 : 5000 }}" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.correccion.valor" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.valor').join(' ')"></span></label>
                        @endif
                    </fieldset>
                    <label class="block" x-show="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen) || mensajes('dictamenes.{{ $bloque }}.justificacion').length">Justificación clínica (obligatoria al observar o descartar)
                        <textarea x-model="dictamenes.{{ $bloque }}.justificacion" :required="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.justificacion" :id="idCampo('dictamenes.{{ $bloque }}.justificacion')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.justificacion').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.justificacion').length ? idCampo('dictamenes.{{ $bloque }}.justificacion') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.justificacion') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.justificacion').length" x-text="mensajes('dictamenes.{{ $bloque }}.justificacion').join(' ')"></span>
                    </label>
                    <p x-show="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" class="text-sm text-amber-900">Este dato declarado no se incorporará a la futura versión.</p>
</div></div>                </fieldset>

@endforeach
</div>
        @if ($versionBase)<fieldset class="space-y-2 border-t border-slate-100 pt-3"><legend class="text-sm font-semibold">Sustituciones explícitas de datos previos</legend><p class="text-xs text-slate-600">Marcá los datos previos que reemplazarás. La versión anterior permanece conservada.</p>
        @foreach (array_intersect_key(\App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS, array_flip(\App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::CAMPOS)) as $campo => $titulo)
            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" value="{{ $campo }}" x-model="sustituciones" data-sustitucion="{{ $campo }}" data-campo="sustituciones_base.{{ $campo }}" :id="idCampo('sustituciones_base.{{ $campo }}')" :aria-invalid="mensajes('sustituciones_base.{{ $campo }}').length ? 'true' : 'false'" :aria-describedby="mensajes('sustituciones_base.{{ $campo }}').length ? idCampo('sustituciones_base.{{ $campo }}') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('sustituciones_base.{{ $campo }}') + '-error'" x-show="mensajes('sustituciones_base.{{ $campo }}').length" x-text="mensajes('sustituciones_base.{{ $campo }}').join(' ')"></span> Sustituir {{ $titulo }}</label>
        @endforeach</fieldset>@endif
    </div></section>
<section id="revision-seccion-declarados" data-seccion="declarados" class="scroll-mt-6 min-w-0 rounded-2xl border border-indigo-100 bg-white p-2 shadow-sm">
    <h2><button type="button" @click="alternar('declarados')" :aria-expanded="abiertas.declarados" aria-controls="revision-panel-declarados" id="revision-titulo-declarados" class="flex min-h-12 w-full flex-wrap items-center gap-3 rounded-xl bg-indigo-50 px-3 py-2 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <span aria-hidden="true" x-text="abiertas.declarados ? '−' : '+'" class="text-xl text-indigo-700"></span><span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">2</span>
        <span class="min-w-0 flex-1 font-semibold text-slate-900">Antecedentes declarados</span>
        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="progreso().secciones[1].estado === 'Completa' ? 'bg-green-100 text-green-900' : progreso().secciones[1].estado === 'Requiere atención' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900'" x-text="progreso().secciones[1].estado"></span>
    </button></h2>
    <div id="revision-panel-declarados" role="region" aria-labelledby="revision-titulo-declarados" x-show="abiertas.declarados" style="display:none" class="min-w-0 space-y-4 p-3">
<div data-tarjetas-revision class="grid min-w-0 gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 28rem), 1fr))">@foreach (array_intersect_key(\App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS, array_flip(['antecedentes', 'cirugias', 'alergias', 'medicacion'])) as $bloque => $titulo)
                @php($esLista = array_key_exists($bloque, \App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::LISTAS))
                <fieldset class="min-w-0 space-y-3 rounded-xl border border-indigo-100 bg-white p-4">
                    <legend class="px-2 font-semibold text-slate-900">{{ $titulo }}</legend><div data-comparacion-declaracion class="grid min-w-0 gap-4" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr))">
                    <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-xs font-medium text-slate-600">Declarado por el paciente</h3>
                        @if ($esLista)
                            @php($respuestaOriginal = $fuente->bloques[$bloque]['respuesta'] ?? null)
                            <p>{{ $respuestaOriginal === 'no' ? 'El paciente declaró No' : ($respuestaOriginal === 'si' ? 'El paciente declaró Sí' : 'Sin respuesta original disponible') }}</p>
                            @if ($fuente->bloques[$bloque]['items'] !== [] || $respuestaOriginal !== 'no')
                                <ul class="space-y-2">@forelse ($fuente->bloques[$bloque]['items'] as $item)<li class="whitespace-pre-line">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@empty<li>Sin información declarada. No acredita «No presenta».</li>@endforelse</ul>
                            @endif
                        @else
                            <p class="whitespace-pre-line">{{ $fuente->bloques[$bloque]['valor'] ?? 'Sin información declarada' }}</p>
                        @endif
                    </div>
<div class="min-w-0 space-y-3"><label class="block text-sm font-medium">Dictamen profesional
                        <select x-model="dictamenes.{{ $bloque }}.dictamen" required class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.dictamen" :id="idCampo('dictamenes.{{ $bloque }}.dictamen')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.dictamen').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.dictamen').length ? idCampo('dictamenes.{{ $bloque }}.dictamen') + '-error' : null"><option value="">Elegí un dictamen</option>@foreach (['VALIDAR' => 'Validar', 'CORREGIR' => 'Corregir', 'OBSERVAR' => 'Observar', 'DESCARTAR' => 'Descartar'] as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.dictamen') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.dictamen').length" x-text="mensajes('dictamenes.{{ $bloque }}.dictamen').join(' ')"></span>
                    </label>
                    @if ($bloque === 'antecedentes')
                        <fieldset class="space-y-3" x-show="dictamenes.antecedentes.dictamen === 'VALIDAR'" :disabled="dictamenes.antecedentes.dictamen !== 'VALIDAR'">
                            <legend class="font-semibold">Clasificación profesional de cada antecedente</legend><p class="text-sm text-red-800" role="alert" x-show="mensajes('dictamenes.antecedentes.clasificacion').length" x-text="mensajes('dictamenes.antecedentes.clasificacion').join(' ')"></p>
                            @foreach ($fuente->bloques[$bloque]['items'] as $item)
                                <label class="block">Ítem #{{ $item['item_origen_id'] }} — {{ $item['descripcion'] }}
                                    <select x-model="dictamenes.antecedentes.clasificacion[{{ $item['item_origen_id'] }}]" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}" :id="idCampo('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}')" :aria-invalid="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').length ? idCampo('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}') + '-error' : null"><option value="">Sin clasificar</option><option value="personales">Antecedente personal</option><option value="familiares">Antecedente familiar</option></select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}') + '-error'" x-show="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').length" x-text="mensajes('dictamenes.antecedentes.clasificacion.{{ $item['item_origen_id'] }}').join(' ')"></span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                    <fieldset class="space-y-4" x-show="dictamenes.{{ $bloque }}.dictamen === 'CORREGIR'" :disabled="dictamenes.{{ $bloque }}.dictamen !== 'CORREGIR'">
                        <legend class="font-semibold">Corrección profesional — conserva el original</legend><p class="text-sm text-red-800" role="alert" x-show="mensajes('dictamenes.{{ $bloque }}.correccion').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion').join(' ')"></p>
                        @if ($esLista)
                            <template x-for="(item, index) in dictamenes.{{ $bloque }}.correccion.items" :key="index">
                                <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                                    <label class="block">Ítem original relacionado
                                        <select x-model="item.item_origen_id" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id') + '-error' : null"><option value="">Nuevo aporte profesional</option>@foreach ($fuente->bloques[$bloque]['items'] as $item)<option value="{{ $item['item_origen_id'] }}">Ítem #{{ $item['item_origen_id'] }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.item_origen_id').join(' ')"></span>
                                    </label>
                                    <label class="block">Tipo de antecedente
                                        <select x-model="item.tipo_destino" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino') + '-error' : null"><option value="">Seleccioná el tipo</option>@foreach ($bloque === 'antecedentes' ? ['personales' => 'Antecedente personal', 'familiares' => 'Antecedente familiar'] : [$bloque => $titulo] as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.tipo_destino').join(' ')"></span>
                                    </label>
                                    <label class="block">Descripción corregida<textarea x-model="item.descripcion" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.descripcion').join(' ')"></span></label>
                                    <label class="block">Procedencia del dato corregido<select x-model="item.procedencia" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia'" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').length ? idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia') + '-error' : null"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.items.' + index + '.procedencia').join(' ')"></span></label>
                                    <button type="button" @click="dictamenes.{{ $bloque }}.correccion.items.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar del resultado corregido</button>
                                </div>
                            </template>
                            <button type="button" @click="agregarCorreccion('{{ $bloque }}')" class="min-h-11 font-semibold text-indigo-700 underline">Agregar dato corregido</button>
                            <p class="text-sm">Los antecedentes admitidos quedan Pendientes de codificación.</p>
                        @elseif ($bloque === 'nivel_actividad_fisica')
                            <label class="block">Actividad física corregida<select x-model="dictamenes.{{ $bloque }}.correccion.valor" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.correccion.valor" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error' : null"><option value="">Sin informar</option><option value="SEDENTARIO">Sedentario</option><option value="MODERADO">Moderado</option><option value="ACTIVO">Activo</option></select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.valor').join(' ')"></span></label>
                        @elseif (in_array($bloque, ['peso_kg', 'altura_cm'], true))
                            <label class="block">Valor corregido (hasta dos decimales)<input type="number" step="0.01" min="{{ $bloque === 'peso_kg' ? 1 : 30 }}" max="{{ $bloque === 'peso_kg' ? 500 : 250 }}" x-model="dictamenes.{{ $bloque }}.correccion.valor" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.correccion.valor" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.valor').join(' ')"></span></label>
                        @else
                            <label class="block">Valor corregido<textarea x-model="dictamenes.{{ $bloque }}.correccion.valor" maxlength="{{ $bloque === 'ocupacion' ? 255 : 5000 }}" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.correccion.valor" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length ? idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.correccion.valor') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.correccion.valor').length" x-text="mensajes('dictamenes.{{ $bloque }}.correccion.valor').join(' ')"></span></label>
                        @endif
                    </fieldset>
                    <label class="block" x-show="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen) || mensajes('dictamenes.{{ $bloque }}.justificacion').length">Justificación clínica (obligatoria al observar o descartar)
                        <textarea x-model="dictamenes.{{ $bloque }}.justificacion" :required="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300" data-campo="dictamenes.{{ $bloque }}.justificacion" :id="idCampo('dictamenes.{{ $bloque }}.justificacion')" :aria-invalid="mensajes('dictamenes.{{ $bloque }}.justificacion').length ? 'true' : 'false'" :aria-describedby="mensajes('dictamenes.{{ $bloque }}.justificacion').length ? idCampo('dictamenes.{{ $bloque }}.justificacion') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('dictamenes.{{ $bloque }}.justificacion') + '-error'" x-show="mensajes('dictamenes.{{ $bloque }}.justificacion').length" x-text="mensajes('dictamenes.{{ $bloque }}.justificacion').join(' ')"></span>
                    </label>
                    <p x-show="['OBSERVAR', 'DESCARTAR'].includes(dictamenes.{{ $bloque }}.dictamen)" class="text-sm text-amber-900">Este dato declarado no se incorporará a la futura versión.</p>
</div></div>                </fieldset>

@endforeach
</div></div></section>
@include('historia-clinica.revision-complementos', ['redisenioRevision' => true])
<noscript><p role="alert">Activá JavaScript para preparar los dictámenes y la vista previa.</p></noscript>
        <p class="text-sm text-slate-600">La vista previa no confirma la revisión, no abre una historia ni cambia el turno.</p>
        <button type="submit" data-acceso-previa="estrecho" class="min-h-11 rounded-xl bg-indigo-700 px-6 py-3 font-semibold text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 xl:hidden">Ver vista previa de incorporación</button>
    </div>
    <aside class="min-w-0 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm xl:sticky xl:top-6" aria-label="Progreso de la revisión">
        <details :open="progresoAbierto" @toggle="progresoAbierto = $el.open"><summary class="cursor-pointer text-xl font-semibold text-slate-900">Progreso de la revisión</summary>
            <div class="mt-4 space-y-4">
                <div aria-live="polite"><p class="text-lg font-semibold"><span x-text="progreso().completas"></span> de 4 secciones completas</p><p class="text-sm text-slate-600" x-text="progreso().porcentaje + '%' "></p>
                    <div role="progressbar" aria-label="Secciones completas" aria-valuemin="0" aria-valuemax="4" :aria-valuenow="progreso().completas" class="mt-2 h-3 overflow-hidden rounded-full bg-indigo-100"><div class="h-full rounded-full bg-indigo-600" :style="{ width: progreso().porcentaje + '%' }"></div></div>
                </div>
                <dl class="divide-y divide-indigo-100 rounded-xl bg-indigo-50 px-3 text-sm">@foreach (['VALIDAR' => 'Validados', 'CORREGIR' => 'Corregidos', 'OBSERVAR' => 'Observados', 'DESCARTAR' => 'Descartados'] as $valor => $etiqueta)<div class="flex justify-between py-2"><dt>{{ $etiqueta }}</dt><dd class="font-semibold" x-text="progreso().contadores.{{ $valor }}"></dd></div>@endforeach<div class="flex justify-between py-2"><dt>Requisitos pendientes</dt><dd class="font-semibold" x-text="progreso().problemas.length"></dd></div></dl>
                <nav aria-label="Secciones de la revisión" class="space-y-2"><template x-for="seccion in progreso().secciones" :key="seccion.id"><button type="button" @click="navegar(seccion.id)" class="flex min-h-11 w-full items-center justify-between gap-2 border-b border-indigo-50 text-left text-sm text-indigo-700"><span x-text="seccion.titulo"></span><span class="text-xs" x-text="seccion.estado"></span></button></template></nav>
                <div><h3 class="font-semibold">Campos pendientes</h3><ul class="mt-2 max-h-64 space-y-2 overflow-y-auto"><template x-for="problema in progreso().problemas" :key="problema.campo"><li><button type="button" @click="problema.seccion ? navegar(problema.seccion, problema.campo) : $root.querySelector('#revision-errores-generales').focus()" class="min-h-11 w-full text-left text-sm underline" :class="problema.error ? 'text-red-800' : 'text-indigo-700'" x-text="textoPendiente(problema)"></button></li></template></ul></div>
                <button type="button" @click="irPrimerPendiente()" class="min-h-11 w-full rounded-xl bg-indigo-700 px-4 py-3 font-semibold text-white focus:ring-2 focus:ring-indigo-500">Ir al primer pendiente</button>
                <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">La declaración original se conserva siempre. El progreso es orientativo; el servidor valida el resultado antes de incorporarlo.</p>
            </div>
        </details>
        <button type="submit" data-acceso-previa="escritorio" class="mt-4 hidden min-h-11 w-full rounded-xl border border-indigo-600 px-4 py-3 text-sm font-semibold text-indigo-700 xl:block">Ver vista previa de incorporación</button>
    </aside>
</form>

@endif
