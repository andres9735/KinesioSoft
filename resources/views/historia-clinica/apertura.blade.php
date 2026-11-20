<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Abrir historia clínica</h1></x-slot>
    <section class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('historia-clinica.desde-turno', $turno->id) }}" class="font-semibold text-indigo-700 underline">Volver al expediente</a>
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">{{ trim($paciente->user->name.' '.$paciente->user->apellido) }}</h2>
            <p class="mt-2">Paciente del turno del {{ $turno->inicio_at->format('d/m/Y H:i') }}. La confirmación crea una historia activa y conserva esta primera versión profesional.</p>
            <p class="mt-2 text-sm text-gray-600">Los cinco bloques de antecedentes y el relevamiento del núcleo familiar requieren una respuesta. Los datos complementarios son opcionales.</p>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-900">
                <h2 class="font-semibold">Revisá los datos antes de confirmar</h2>
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="{{ route('historia-clinica.apertura.store', $turno->id) }}" class="space-y-6">
            @csrf
            <input type="hidden" name="contexto" value="{{ old('contexto', $contexto) }}">
            <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <label class="block" for="especialidad">Especialidad de la profesional</label>
                <select id="especialidad" name="especialidad_id" required class="w-full rounded-lg border-gray-300">
                    <option value="">Seleccioná tu especialidad</option>
                    @foreach ($especialidades as $especialidad)
                        <option value="{{ $especialidad->id }}" @selected(old('especialidad_id', $especialidades->count() === 1 ? $especialidad->id : null) == $especialidad->id)>{{ $especialidad->nombre }}</option>
                    @endforeach
                </select>
                <label class="block" for="procedencia">Procedencia del relevamiento y del núcleo familiar</label>
                <select id="procedencia" name="procedencia_relevamiento" required class="w-full rounded-lg border-gray-300">
                    <option value="">Seleccioná la procedencia</option>
                    @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('procedencia_relevamiento') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $titulo)
                @php
                    $items = old('bloques.'.$tipo.'.items', [['descripcion' => '', 'procedencia' => '']]);
                    $items = is_array($items) ? array_values($items) : [];
                @endphp
                <fieldset class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" x-data="{ resultado: @js(old('bloques.'.$tipo.'.resultado', '')), items: @js($items) }">
                    <legend class="px-2 text-lg font-semibold">{{ $titulo }}</legend>
                    <label for="resultado-{{ $tipo }}" class="block">Resultado del relevamiento</label>
                    <select id="resultado-{{ $tipo }}" name="bloques[{{ $tipo }}][resultado]" x-model="resultado" required class="w-full rounded-lg border-gray-300">
                        <option value="">Seleccioná una respuesta</option>
                        @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <fieldset x-show="resultado === 'INFORMADO'" :disabled="resultado !== 'INFORMADO'" class="space-y-4">
                        <p class="text-sm text-gray-700">Los antecedentes presentes se conservarán como <strong>Pendiente de codificación</strong>. Se podrá precisar su nomenclatura mediante una actualización posterior.</p>
                        <template x-for="(item, index) in items" :key="index">
                            <div class="space-y-3 rounded-lg border border-gray-200 p-4">
                                <label class="block">Descripción clínica
                                    <textarea :name="'bloques[{{ $tipo }}][items][' + index + '][descripcion]'" x-model="item.descripcion" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300"></textarea>
                                </label>
                                <label class="block">Procedencia del antecedente
                                    <select :name="'bloques[{{ $tipo }}][items][' + index + '][procedencia]'" x-model="item.procedencia" class="mt-1 w-full rounded-lg border-gray-300">
                                        <option value="">Seleccioná la procedencia</option>
                                        @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <input type="hidden" :name="'bloques[{{ $tipo }}][items][' + index + '][estado_codificacion]'" value="PENDIENTE_CODIFICACION">
                                <button type="button" @click="items.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar antecedente</button>
                            </div>
                        </template>
                        <button type="button" @click="items.push({ descripcion: '', procedencia: '' })" class="min-h-11 font-semibold text-indigo-700 underline">Agregar antecedente</button>
                    </fieldset>
                </fieldset>
            @endforeach

            @php
                $integrantes = old('familia.integrantes', [['nombre_apellido' => '', 'vinculo' => '']]);
                $integrantes = is_array($integrantes) ? array_values($integrantes) : [];
            @endphp
            <fieldset class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" x-data="{ resultado: @js(old('familia.resultado', '')), integrantes: @js($integrantes), contacto: @js((bool) old('familia.incluir_contacto', false)) }">
                <legend class="px-2 text-lg font-semibold">Núcleo familiar</legend>
                <p class="text-sm text-gray-600">Identificación de integrantes. La información clínica familiar corresponde al bloque Antecedentes familiares.</p>
                <label for="resultado-familia" class="block">Resultado del relevamiento familiar</label>
                <select id="resultado-familia" name="familia[resultado]" x-model="resultado" required class="w-full rounded-lg border-gray-300">
                    <option value="">Seleccioná una respuesta</option>
                    @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::FAMILIA as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                <label class="block">Motivo de información no disponible, si se conoce
                    <textarea name="familia[motivo]" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-300">{{ old('familia.motivo') }}</textarea>
                </label>
                <fieldset x-show="resultado === 'INFORMADO'" :disabled="resultado !== 'INFORMADO'" class="space-y-4">
                    <template x-for="(integrante, index) in integrantes" :key="index">
                        <div class="space-y-3 rounded-lg border border-gray-200 p-4">
                            <label class="block">Nombre y apellido (puede quedar no disponible)
                                <input type="text" :name="'familia[integrantes][' + index + '][nombre_apellido]'" x-model="integrante.nombre_apellido" maxlength="255" class="mt-1 w-full rounded-lg border-gray-300">
                            </label>
                            <label class="block">Vínculo con el paciente
                                <input type="text" :name="'familia[integrantes][' + index + '][vinculo]'" x-model="integrante.vinculo" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300">
                            </label>
                            <button type="button" @click="integrantes.splice(index, 1)" class="min-h-11 font-semibold text-red-700 underline">Quitar integrante</button>
                        </div>
                    </template>
                    <button type="button" @click="integrantes.push({ nombre_apellido: '', vinculo: '' })" class="min-h-11 font-semibold text-indigo-700 underline">Agregar integrante</button>
                    @if ($paciente->contacto_emergencia_nombre)
                        <div class="space-y-3 rounded-lg bg-gray-50 p-4">
                            <p>Contacto de emergencia registrado: <strong>{{ $paciente->contacto_emergencia_nombre }}</strong>.</p>
                            <label class="flex items-center gap-3"><input type="checkbox" name="familia[incluir_contacto]" value="1" x-model="contacto"> Confirmo que el contacto integra el núcleo familiar</label>
                            <label class="block" x-show="contacto">Vínculo del contacto
                                <input name="familia[vinculo_contacto]" value="{{ old('familia.vinculo_contacto') }}" maxlength="100" :disabled="!contacto" class="mt-1 w-full rounded-lg border-gray-300">
                            </label>
                        </div>
                    @endif
                </fieldset>
            </fieldset>

            <fieldset class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <legend class="px-2 text-lg font-semibold">Datos complementarios (opcionales)</legend>
                <label class="block">Objetivo de salud<textarea name="objetivo_salud" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300">{{ old('objetivo_salud') }}</textarea></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>Peso (kg)<input type="number" name="peso_kg" value="{{ old('peso_kg') }}" min="1" max="500" step="0.01" class="mt-1 w-full rounded-lg border-gray-300"></label>
                    <label>Altura (cm)<input type="number" name="altura_cm" value="{{ old('altura_cm') }}" min="30" max="250" class="mt-1 w-full rounded-lg border-gray-300"></label>
                </div>
                <label class="block">Ocupación<input name="ocupacion" value="{{ old('ocupacion') }}" maxlength="255" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="block">Actividad física
                    <select name="nivel_actividad_fisica" class="mt-1 w-full rounded-lg border-gray-300">
                        <option value="">Sin informar</option>
                        @foreach (['SEDENTARIO' => 'Sedentario', 'MODERADO' => 'Moderado', 'ACTIVO' => 'Activo'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('nivel_actividad_fisica') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">Detalle de actividad física<textarea name="detalle_actividad_fisica" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300">{{ old('detalle_actividad_fisica') }}</textarea></label>
            </fieldset>
            <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <label class="flex items-start gap-3"><input type="checkbox" name="confirmar" value="1" required class="mt-1" @checked(old('confirmar'))> Confirmo el relevamiento y la apertura de la historia clínica de este paciente con mi autoría profesional.</label>
                <p class="text-sm text-gray-600">Esta acción no inicia la atención ni modifica el turno. La revisión de declaraciones se realiza por separado.</p>
                <noscript><p role="alert">Activá JavaScript para completar los antecedentes y el núcleo familiar.</p></noscript>
                <button type="submit" class="min-h-11 rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white hover:bg-indigo-800 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Confirmar apertura</button>
            </div>
        </form>
    </section>
</x-app-layout>
