@if (!($redisenioRevision ?? false))
<section class="space-y-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-xl font-semibold">Relevamientos complementarios profesionales</h2>
    <p>Una lista vacía no declara ausencia. Los aportes se suman a la información admitida o previa; para sustituir datos previos, indicá el reemplazo expresamente.</p>
    <p class="text-sm text-slate-600" x-text="respuestaAntecedentesGenerales()"></p>
    @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $titulo)
        <fieldset class="space-y-3 border-t border-gray-200 pt-4">
            <legend class="px-2 font-semibold">{{ $titulo }}</legend>
            @include('historia-clinica.revision-resumen-fuentes')
            <label class="block">Resultado del relevamiento complementario
                <select x-model="bloques.{{ $tipo }}.resultado" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Sin aporte complementario; conservar el resultado admitido o previo</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select>
            </label>
            <fieldset class="space-y-3" x-show="bloques.{{ $tipo }}.resultado === 'INFORMADO'" :disabled="bloques.{{ $tipo }}.resultado !== 'INFORMADO'">
                <template x-for="(item, index) in bloques.{{ $tipo }}.items" :key="index">
                    <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                        <label class="block">Descripción clínica<textarea x-model="item.descripcion" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label>
                        <label class="block">Procedencia<select x-model="item.procedencia" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></label>
                        <p class="text-sm">Pendiente de codificación</p>
                        <button type="button" @click="bloques.{{ $tipo }}.items.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar aporte</button>
                    </div>
                </template>
                <button type="button" @click="agregarAporte('{{ $tipo }}')" class="min-h-11 font-semibold text-indigo-700 underline">Agregar antecedente complementario</button>
            </fieldset>
        </fieldset>
    @endforeach
    <fieldset class="space-y-4 border-t border-gray-200 pt-4">
        <legend class="px-2 text-lg font-semibold">Núcleo familiar</legend>
        <label class="flex items-start gap-3"><input type="checkbox" x-model="actualizarFamilia"> Registrar o actualizar el relevamiento familiar</label>
        <p class="text-sm">Sin aporte se conserva el núcleo familiar previo. Si todavía no hay versión oficial, este relevamiento debe completarse. La información clínica familiar corresponde a Antecedentes familiares.</p>
        <fieldset class="space-y-4" x-show="actualizarFamilia" :disabled="!actualizarFamilia">
            <label class="block">Resultado del relevamiento familiar<select x-model="familia.resultado" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Seleccioná una respuesta</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::FAMILIA as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select></label>
            <label class="block">Motivo de información no disponible, si se conoce<textarea x-model="familia.motivo" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label>
            <fieldset class="space-y-4" x-show="familia.resultado === 'INFORMADO'" :disabled="familia.resultado !== 'INFORMADO'">
                <template x-for="(integrante, index) in familia.integrantes" :key="index">
                    <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                        <label class="block">Nombre y apellido (puede quedar no disponible)<input x-model="integrante.nombre_apellido" maxlength="255" class="mt-1 w-full rounded-lg border-gray-300"></label>
                        <label class="block">Vínculo con el paciente<input x-model="integrante.vinculo" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300"></label>
                        <button type="button" @click="familia.integrantes.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar integrante del resultado</button>
                    </div>
                </template>
                <button type="button" @click="familia.integrantes.push({ nombre_apellido: '', vinculo: '' })" class="min-h-11 font-semibold text-indigo-700 underline">Agregar integrante</button>
                @if ($paciente->contacto_emergencia_nombre)
                    <div class="space-y-3 rounded-xl bg-gray-50 p-4">
                        <p>Contacto de emergencia registrado: <strong>{{ $paciente->contacto_emergencia_nombre }}</strong>.</p>
                        <label class="flex items-start gap-3"><input type="checkbox" x-model="familia.incluir_contacto"> Confirmo que esta persona integra el núcleo familiar</label>
                        <label class="block" x-show="familia.incluir_contacto">Vínculo del contacto<input x-model="familia.vinculo_contacto" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300"></label>
                    </div>
                @endif
            </fieldset>
        </fieldset>
    </fieldset>
    @if ($versionBase)
        <fieldset class="space-y-3 border-t border-gray-200 pt-4">
            <legend class="px-2 text-lg font-semibold">Sustituciones explícitas de datos previos</legend>
            <p>Marcá únicamente los apartados que reemplazarás en la futura versión. La versión anterior permanece conservada. El relevamiento resultante debe quedar resuelto.</p>
            @foreach ([...\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS, ...array_intersect_key(\App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS, array_flip(\App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::CAMPOS)), 'familia' => 'Núcleo familiar'] as $campo => $titulo)
                <label class="flex items-start gap-3"><input type="checkbox" value="{{ $campo }}" x-model="sustituciones"> Sustituir {{ $titulo }}</label>
            @endforeach
        </fieldset>
    @endif
</section>

@else
<section id="revision-seccion-complementos" data-seccion="complementos" class="scroll-mt-6 min-w-0 rounded-2xl border border-indigo-100 bg-white p-2 shadow-sm">
    <h2><button type="button" @click="alternar('complementos')" :aria-expanded="abiertas.complementos" aria-controls="revision-panel-complementos" id="revision-titulo-complementos" class="flex min-h-12 w-full flex-wrap items-center gap-3 rounded-xl bg-indigo-50 px-3 py-2 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <span aria-hidden="true" x-text="abiertas.complementos ? '−' : '+'" class="text-xl text-indigo-700"></span><span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">3</span>
        <span class="min-w-0 flex-1 font-semibold text-slate-900">Relevamientos complementarios</span>
        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="progreso().secciones[2].estado === 'Completa' ? 'bg-green-100 text-green-900' : progreso().secciones[2].estado === 'Requiere atención' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900'" x-text="progreso().secciones[2].estado"></span>
    </button></h2>
    <div id="revision-panel-complementos" role="region" aria-labelledby="revision-titulo-complementos" x-show="abiertas.complementos" style="display:none" class="min-w-0 space-y-4 p-3">
<p class="text-sm text-slate-600">Una lista vacía no declara ausencia. Los aportes se suman a la información admitida o previa; para sustituir datos previos, indicá el reemplazo expresamente.</p>
<p class="text-sm text-slate-600" x-text="respuestaAntecedentesGenerales()"></p>
<div class="grid min-w-0 gap-3 md:grid-cols-2">    @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $titulo)
        <fieldset class="space-y-3 rounded-xl border border-indigo-100 bg-slate-50 p-4">
            <legend class="px-2 font-semibold">{{ $titulo }}</legend><p class="text-sm text-red-800" role="alert" x-show="mensajes('bloques.{{ $tipo }}').length" x-text="mensajes('bloques.{{ $tipo }}').join(' ')"></p>
            @include('historia-clinica.revision-resumen-fuentes')
            <label class="block">Resultado del relevamiento complementario
                <select x-model="bloques.{{ $tipo }}.resultado" class="mt-1 w-full rounded-lg border-gray-300" data-campo="bloques.{{ $tipo }}.resultado" :id="idCampo('bloques.{{ $tipo }}.resultado')" :aria-invalid="mensajes('bloques.{{ $tipo }}.resultado').length ? 'true' : 'false'" :aria-describedby="mensajes('bloques.{{ $tipo }}.resultado').length ? idCampo('bloques.{{ $tipo }}.resultado') + '-error' : null"><option value="">Sin aporte complementario; conservar el resultado admitido o previo</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('bloques.{{ $tipo }}.resultado') + '-error'" x-show="mensajes('bloques.{{ $tipo }}.resultado').length" x-text="mensajes('bloques.{{ $tipo }}.resultado').join(' ')"></span>
            </label>
            <fieldset class="space-y-3" x-show="bloques.{{ $tipo }}.resultado === 'INFORMADO'" :disabled="bloques.{{ $tipo }}.resultado !== 'INFORMADO'">
                <template x-for="(item, index) in bloques.{{ $tipo }}.items" :key="index">
                    <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                        <label class="block">Descripción clínica<textarea x-model="item.descripcion" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'bloques.{{ $tipo }}.items.' + index + '.descripcion'" :id="idCampo('bloques.{{ $tipo }}.items.' + index + '.descripcion')" :aria-invalid="mensajes('bloques.{{ $tipo }}.items.' + index + '.descripcion').length ? 'true' : 'false'" :aria-describedby="mensajes('bloques.{{ $tipo }}.items.' + index + '.descripcion').length ? idCampo('bloques.{{ $tipo }}.items.' + index + '.descripcion') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('bloques.{{ $tipo }}.items.' + index + '.descripcion') + '-error'" x-show="mensajes('bloques.{{ $tipo }}.items.' + index + '.descripcion').length" x-text="mensajes('bloques.{{ $tipo }}.items.' + index + '.descripcion').join(' ')"></span></label>
                        <label class="block">Procedencia<select x-model="item.procedencia" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'bloques.{{ $tipo }}.items.' + index + '.procedencia'" :id="idCampo('bloques.{{ $tipo }}.items.' + index + '.procedencia')" :aria-invalid="mensajes('bloques.{{ $tipo }}.items.' + index + '.procedencia').length ? 'true' : 'false'" :aria-describedby="mensajes('bloques.{{ $tipo }}.items.' + index + '.procedencia').length ? idCampo('bloques.{{ $tipo }}.items.' + index + '.procedencia') + '-error' : null"><option value="">Seleccioná la procedencia</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('bloques.{{ $tipo }}.items.' + index + '.procedencia') + '-error'" x-show="mensajes('bloques.{{ $tipo }}.items.' + index + '.procedencia').length" x-text="mensajes('bloques.{{ $tipo }}.items.' + index + '.procedencia').join(' ')"></span></label>
                        <p class="text-sm">Pendiente de codificación</p>
                        <button type="button" @click="bloques.{{ $tipo }}.items.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar aporte</button>
                    </div>
                </template>
                <button type="button" data-campo="bloques.{{ $tipo }}.items" @click="agregarAporte('{{ $tipo }}')" class="min-h-11 font-semibold text-indigo-700 underline">Agregar antecedente complementario</button><p class="text-sm text-red-800" role="alert" x-show="mensajes('bloques.{{ $tipo }}.items').length" x-text="mensajes('bloques.{{ $tipo }}.items').join(' ')"></p>
            </fieldset>
@if ($versionBase)<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" value="{{ $tipo }}" x-model="sustituciones" data-sustitucion="{{ $tipo }}" data-campo="sustituciones_base.{{ $tipo }}" :id="idCampo('sustituciones_base.{{ $tipo }}')" :aria-invalid="mensajes('sustituciones_base.{{ $tipo }}').length ? 'true' : 'false'" :aria-describedby="mensajes('sustituciones_base.{{ $tipo }}').length ? idCampo('sustituciones_base.{{ $tipo }}') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('sustituciones_base.{{ $tipo }}') + '-error'" x-show="mensajes('sustituciones_base.{{ $tipo }}').length" x-text="mensajes('sustituciones_base.{{ $tipo }}').join(' ')"></span> Sustituir {{ $titulo }} previos</label>@endif
        </fieldset>
    @endforeach
</div></div></section>
<section id="revision-seccion-familia" data-seccion="familia" class="scroll-mt-6 min-w-0 rounded-2xl border border-indigo-100 bg-white p-2 shadow-sm">
    <h2><button type="button" @click="alternar('familia')" :aria-expanded="abiertas.familia" aria-controls="revision-panel-familia" id="revision-titulo-familia" class="flex min-h-12 w-full flex-wrap items-center gap-3 rounded-xl bg-indigo-50 px-3 py-2 text-left focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <span aria-hidden="true" x-text="abiertas.familia ? '−' : '+'" class="text-xl text-indigo-700"></span><span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">4</span>
        <span class="min-w-0 flex-1 font-semibold text-slate-900">Núcleo familiar</span>
        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="progreso().secciones[3].estado === 'Completa' ? 'bg-green-100 text-green-900' : progreso().secciones[3].estado === 'Requiere atención' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900'" x-text="progreso().secciones[3].estado"></span>
    </button></h2>
    <div id="revision-panel-familia" role="region" aria-labelledby="revision-titulo-familia" x-show="abiertas.familia" style="display:none" class="min-w-0 space-y-4 p-3">
    <fieldset class="space-y-4">

        <label class="flex items-start gap-3"><input type="checkbox" x-model="actualizarFamilia" data-campo="familia" :id="idCampo('familia')" :aria-invalid="mensajes('familia').length ? 'true' : 'false'" :aria-describedby="mensajes('familia').length ? idCampo('familia') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia') + '-error'" x-show="mensajes('familia').length" x-text="mensajes('familia').join(' ')"></span> Registrar o actualizar el relevamiento familiar</label><p class="text-sm text-red-800" role="alert" x-show="mensajes('familia').length" x-text="mensajes('familia').join(' ')"></p>
        <p class="text-sm">Sin aporte se conserva el núcleo familiar previo. Si todavía no hay versión oficial, este relevamiento debe completarse. La información clínica familiar corresponde a Antecedentes familiares.</p>
        <fieldset class="space-y-4" x-show="actualizarFamilia" :disabled="!actualizarFamilia">
            <label class="block">Resultado del relevamiento familiar<select x-model="familia.resultado" class="mt-1 w-full rounded-lg border-gray-300" data-campo="familia.resultado" :id="idCampo('familia.resultado')" :aria-invalid="mensajes('familia.resultado').length ? 'true' : 'false'" :aria-describedby="mensajes('familia.resultado').length ? idCampo('familia.resultado') + '-error' : null"><option value="">Seleccioná una respuesta</option>@foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::FAMILIA as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</select><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia.resultado') + '-error'" x-show="mensajes('familia.resultado').length" x-text="mensajes('familia.resultado').join(' ')"></span></label>
            <label class="block" x-show="familia.resultado === 'INFORMACION_NO_DISPONIBLE'">Motivo de información no disponible, si se conoce<textarea x-model="familia.motivo" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-300" data-campo="familia.motivo" :id="idCampo('familia.motivo')" :aria-invalid="mensajes('familia.motivo').length ? 'true' : 'false'" :aria-describedby="mensajes('familia.motivo').length ? idCampo('familia.motivo') + '-error' : null"></textarea><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia.motivo') + '-error'" x-show="mensajes('familia.motivo').length" x-text="mensajes('familia.motivo').join(' ')"></span></label>
            <fieldset class="space-y-4" x-show="familia.resultado === 'INFORMADO'" :disabled="familia.resultado !== 'INFORMADO'">
                <template x-for="(integrante, index) in familia.integrantes" :key="index">
                    <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                        <label class="block">Nombre y apellido (puede quedar no disponible)<input x-model="integrante.nombre_apellido" maxlength="255" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'familia.integrantes.' + index + '.nombre_apellido'" :id="idCampo('familia.integrantes.' + index + '.nombre_apellido')" :aria-invalid="mensajes('familia.integrantes.' + index + '.nombre_apellido').length ? 'true' : 'false'" :aria-describedby="mensajes('familia.integrantes.' + index + '.nombre_apellido').length ? idCampo('familia.integrantes.' + index + '.nombre_apellido') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia.integrantes.' + index + '.nombre_apellido') + '-error'" x-show="mensajes('familia.integrantes.' + index + '.nombre_apellido').length" x-text="mensajes('familia.integrantes.' + index + '.nombre_apellido').join(' ')"></span></label>
                        <label class="block">Vínculo con el paciente<input x-model="integrante.vinculo" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300" :data-campo="'familia.integrantes.' + index + '.vinculo'" :id="idCampo('familia.integrantes.' + index + '.vinculo')" :aria-invalid="mensajes('familia.integrantes.' + index + '.vinculo').length ? 'true' : 'false'" :aria-describedby="mensajes('familia.integrantes.' + index + '.vinculo').length ? idCampo('familia.integrantes.' + index + '.vinculo') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia.integrantes.' + index + '.vinculo') + '-error'" x-show="mensajes('familia.integrantes.' + index + '.vinculo').length" x-text="mensajes('familia.integrantes.' + index + '.vinculo').join(' ')"></span></label>
                        <button type="button" @click="familia.integrantes.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar integrante del resultado</button>
                    </div>
                </template>
                <button type="button" data-campo="familia.integrantes" @click="familia.integrantes.push({ nombre_apellido: '', vinculo: '' })" class="min-h-11 font-semibold text-indigo-700 underline">Agregar integrante</button><p class="text-sm text-red-800" role="alert" x-show="mensajes('familia.integrantes').length" x-text="mensajes('familia.integrantes').join(' ')"></p>
                @if ($paciente->contacto_emergencia_nombre)
                    <div class="space-y-3 rounded-xl bg-gray-50 p-4">
                        <p>Contacto de emergencia registrado: <strong>{{ $paciente->contacto_emergencia_nombre }}</strong>.</p>
                        <label class="flex items-start gap-3"><input type="checkbox" x-model="familia.incluir_contacto" data-campo="familia.incluir_contacto" :id="idCampo('familia.incluir_contacto')" :aria-invalid="mensajes('familia.incluir_contacto').length ? 'true' : 'false'" :aria-describedby="mensajes('familia.incluir_contacto').length ? idCampo('familia.incluir_contacto') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia.incluir_contacto') + '-error'" x-show="mensajes('familia.incluir_contacto').length" x-text="mensajes('familia.incluir_contacto').join(' ')"></span> Confirmo que esta persona integra el núcleo familiar</label>
                        <label class="block" x-show="familia.incluir_contacto">Vínculo del contacto<input x-model="familia.vinculo_contacto" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300" data-campo="familia.vinculo_contacto" :id="idCampo('familia.vinculo_contacto')" :aria-invalid="mensajes('familia.vinculo_contacto').length ? 'true' : 'false'" :aria-describedby="mensajes('familia.vinculo_contacto').length ? idCampo('familia.vinculo_contacto') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('familia.vinculo_contacto') + '-error'" x-show="mensajes('familia.vinculo_contacto').length" x-text="mensajes('familia.vinculo_contacto').join(' ')"></span></label>
                    </div>
                @endif
            </fieldset>
        </fieldset>
    </fieldset>
@if ($versionBase)<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" value="familia" x-model="sustituciones" data-sustitucion="familia" data-campo="sustituciones_base.familia" :id="idCampo('sustituciones_base.familia')" :aria-invalid="mensajes('sustituciones_base.familia').length ? 'true' : 'false'" :aria-describedby="mensajes('sustituciones_base.familia').length ? idCampo('sustituciones_base.familia') + '-error' : null"><span class="mt-1 block text-sm text-red-800" role="alert" :id="idCampo('sustituciones_base.familia') + '-error'" x-show="mensajes('sustituciones_base.familia').length" x-text="mensajes('sustituciones_base.familia').join(' ')"></span> Sustituir Núcleo familiar previo</label>@endif</div></section>

@endif
