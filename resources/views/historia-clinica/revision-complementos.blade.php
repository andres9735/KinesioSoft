<section class="space-y-6 rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-xl font-semibold">Relevamientos complementarios profesionales</h2>
    <p>Completá los bloques que no queden resueltos con los datos admitidos o la versión previa. Una respuesta vacía no declara ausencia. Los aportes se suman a los antecedentes conservados; para reemplazarlos debés indicarlo expresamente.</p>
    @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $titulo)
        <fieldset class="space-y-3 border-t border-gray-200 pt-4">
            <legend class="px-2 font-semibold">{{ $titulo }}</legend>
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
