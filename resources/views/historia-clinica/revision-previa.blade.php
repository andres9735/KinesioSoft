<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-slate-900">Vista previa de incorporación</h1></x-slot>
    @php
        $etiquetas = \App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS;
        $etiquetasDictamen = ['VALIDAR' => 'Validar', 'CORREGIR' => 'Corregir', 'OBSERVAR' => 'Observar', 'DESCARTAR' => 'Descartar'];
        $conteos = array_fill_keys(array_keys($etiquetasDictamen), 0);
        $categoriasCorregidas = [];
        foreach ($resultado->dictamenes as $decision) {
            $conteos[$decision['dictamen']]++;
            if ($decision['dictamen'] === 'CORREGIR') {
                foreach ($decision['datos_admitidos'] as $itemAdmitido) {
                    if (is_array($itemAdmitido) && isset($itemAdmitido['tipo_destino'])) {
                        $categoriasCorregidas[] = \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS[$itemAdmitido['tipo_destino']];
                    }
                }
            }
        }
        $proyecciones = collect($secciones)->keyBy('titulo');
        $excluidos = collect($resultado->dictamenes)->whereIn('dictamen', ['OBSERVAR', 'DESCARTAR']);
        $camposSalud = \App\Data\HistoriaClinica\FuenteDeclaracionParaRevision::CAMPOS;
        $haySalud = collect($camposSalud)->contains(fn ($campo) => $proyecciones[$etiquetas[$campo]]['despues'] !== []);
    @endphp
    <style>#revision-previa [x-cloak] { display: none !important; }</style>
    <section id="revision-previa" class="mx-auto max-w-screen-2xl space-y-4 px-4 py-6 sm:px-6 lg:px-8" x-data="{ propuestaEditada: false }">
        <a class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-700 underline" href="{{ route('historia-clinica.desde-turno', $turno->id) }}"><span aria-hidden="true">←</span> Volver al expediente sin incorporar</a>
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            <div role="status" class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">
                <svg aria-hidden="true" class="mt-1 h-6 w-6 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 10 18H2L12 3Z" stroke-linejoin="round"/><path d="M12 9v5m0 3v1"/></svg>
                <div><h2 class="text-lg font-semibold">Propuesta sin confirmar — los datos clínicos todavía no fueron incorporados</h2><p class="mt-1">La declaración sigue Pendiente. Consultar esta vista previa no registra una nueva versión clínica.</p></div>
            </div>
            <section aria-label="Contexto de la propuesta" class="space-y-4 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="min-w-0"><dt class="text-sm text-slate-600">Paciente</dt><dd class="break-words text-lg font-semibold text-slate-900">{{ trim($paciente->user->name.' '.$paciente->user->apellido) }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Turno</dt><dd class="font-semibold">{{ $turno->inicio_at->format('d/m/Y') }}</dd><dd>{{ $turno->inicio_at->format('H:i') }} – {{ $turno->fin_at->format('H:i') }}</dd><dd class="break-words text-sm text-slate-600">{{ config('app.timezone') }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Historia clínica</dt><dd class="font-semibold">{{ $historia?->nro_historia_clinica ?? 'Sin historia clínica' }}</dd>@unless ($historia)<dd class="text-sm text-slate-600">La confirmación requerirá crear una única historia clínica activa.</dd>@endunless</div>
                    <div><dt class="text-sm text-slate-600">Declaración</dt><dd class="font-semibold">#{{ $declaracion->id }}</dd><dd class="text-sm text-slate-600">{{ $declaracion->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="min-w-0"><dt class="text-sm text-slate-600">Profesional responsable</dt><dd class="break-words font-semibold">{{ trim($turno->kinesiologa->user->name.' '.$turno->kinesiologa->user->apellido) }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Especialidad</dt><dd class="break-words font-semibold">{{ $especialidad->nombre }}</dd></div>
                </dl>
                <dl class="grid gap-4 border-t border-indigo-100 pt-4 sm:grid-cols-2">
                    <div><dt class="text-sm text-slate-600">Procedencia del relevamiento complementario</dt><dd class="font-semibold">{{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$resultado->datosClinicos['procedencia_relevamiento']] }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Versión propuesta — todavía no registrada</dt><dd class="font-semibold" data-version-propuesta>{{ ($versionBase?->numero_version ?? 0) + 1 }}</dd></div>
                </dl>
                <p class="text-sm text-slate-600">Origen de la futura versión: <strong>Revisión de declaración de salud</strong>.</p>
            </section>

            <section id="previa-resultado" aria-labelledby="previa-resultado-titulo" class="min-w-0 space-y-4 rounded-2xl border border-indigo-100 bg-white p-3 shadow-sm sm:p-5">
                <h2 id="previa-resultado-titulo" class="rounded-xl bg-indigo-50 px-3 py-2 text-xl font-semibold text-slate-900">Resultado clínico propuesto</h2>
                <section class="space-y-3" aria-labelledby="previa-salud">
                    <h3 id="previa-salud" class="font-semibold text-slate-900">Datos de salud y características personales</h3>
                    @if ($haySalud)
                        <dl class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr))" data-valores-propuestos>
                        @foreach ($camposSalud as $campo)
                            @php
                                $proyeccion = $proyecciones[$etiquetas[$campo]];
                                $valorDeclarado = $resultado->dictamenes[$campo]['datos_originales']['valor'];
                                $textoDeclarado = $valorDeclarado !== null && in_array($campo, ['peso_kg', 'altura_cm'], true) && is_numeric($valorDeclarado)
                                    ? number_format((float) $valorDeclarado, 2, '.', '') : $valorDeclarado;
                            @endphp
                            @if ($proyeccion['despues'] !== [])
                                <div class="min-w-0 space-y-2 rounded-xl border border-indigo-100 p-3" data-resultado-campo="{{ $campo }}">
                                    <dt class="font-medium text-slate-700">{{ $etiquetas[$campo] }}</dt>
                                    <dd class="whitespace-pre-line break-words font-semibold text-slate-900">{{ implode("\n", $proyeccion['despues']) }}</dd>
                                    @if ($resultado->dictamenes[$campo]['dictamen'] === 'CORREGIR')
                                        <dd class="space-y-1"><span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-sm font-medium text-amber-900">Corrección profesional</span>
                                        <p class="break-words text-sm text-slate-600">Declarado: {{ $textoDeclarado ?? 'Sin información declarada' }} <span aria-label="a">→</span> Propuesto: {{ implode(' · ', $proyeccion['despues']) }}</p></dd>
                                    @endif
                                    @if ($proyeccion['conservado'] !== [])<dd class="text-sm text-indigo-700">Dato clínico previo conservado</dd>@endif
                                </div>
                            @endif
                        @endforeach
                        </dl>
                    @else
                        <p class="text-slate-600">Sin datos propuestos para incorporar en esta categoría.</p>
                    @endif
                </section>
                @foreach (['Antecedentes y relevamientos complementarios' => array_values(\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS), 'Núcleo familiar' => ['Núcleo familiar']] as $categoria => $titulos)
                    <section class="space-y-3 border-t border-indigo-100 pt-4" aria-label="{{ $categoria }}">
                        <h3 class="font-semibold text-slate-900">{{ $categoria }}</h3>
                        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr))">
                        @foreach ($titulos as $titulo)
                            @php
                                $proyeccion = $proyecciones[$titulo];
                            @endphp
                            <div class="min-w-0 rounded-xl border border-indigo-100 p-3" data-resultado-categoria="{{ $titulo }}">
                                @if ($titulo !== $categoria)<h4 class="mb-2 font-medium text-slate-700">{{ $titulo }}</h4>@endif
                                @if (in_array($titulo, $categoriasCorregidas, true))<p class="mb-2"><span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-sm font-medium text-amber-900">Incluye correcciones profesionales</span></p>@endif
                                <ul class="space-y-2">@foreach ($proyeccion['despues'] as $texto)<li class="whitespace-pre-line break-words">{{ $texto }}</li>@endforeach</ul>
                                @if ($proyeccion['conservado'] !== [])<p class="mt-2 text-sm text-indigo-700">Conservaciones clínicas disponibles en el detalle.</p>@endif
                            </div>
                        @endforeach
                        </div>
                    </section>
                @endforeach
                @if ($versionBase?->anamnesis_fisica !== null)
                    <section class="space-y-2 border-t border-indigo-100 pt-4"><h3 class="font-semibold">Anamnesis física previa (se conserva)</h3><p class="whitespace-pre-line break-words">{{ $versionBase->anamnesis_fisica }}</p></section>
                @endif
            </section>

            <section id="previa-excluidos" aria-labelledby="previa-excluidos-titulo" class="space-y-3 rounded-2xl border border-rose-100 bg-white p-4 shadow-sm sm:p-5">
                <h2 id="previa-excluidos-titulo" class="text-lg font-semibold text-slate-900">Datos no incorporados de la declaración</h2>
                @forelse ($excluidos as $bloque => $dictamen)
                    <details class="rounded-xl border border-rose-100 bg-rose-50 p-3" data-exclusion="{{ $bloque }}">
                        <summary class="min-h-11 cursor-pointer break-words font-medium text-rose-900">{{ $etiquetas[$bloque] }} — {{ $etiquetasDictamen[$dictamen['dictamen']] }}</summary>
                        <p class="mt-2">Dato declarado excluido de la incorporación según el dictamen profesional.</p>
                        <p class="mt-2 whitespace-pre-line break-words">Justificación: {{ $dictamen['observacion_profesional'] }}</p>
                    </details>
                @empty
                    <p class="text-slate-600">No hay dictámenes Observar o Descartar. Las exclusiones de ítems corregidos se detallan en la trazabilidad.</p>
                @endforelse
                <p class="text-sm text-slate-600">La exclusión se refiere al dato declarado.@if ($versionBase) Los datos clínicos previos se conservan salvo sustitución explícita y figuran en el resultado propuesto.@endif</p>
            </section>

            <details id="previa-comparaciones" class="min-w-0 rounded-2xl border border-indigo-100 bg-white p-3 shadow-sm sm:p-5">
                <summary class="min-h-11 cursor-pointer rounded-xl bg-indigo-50 px-3 py-2 text-lg font-semibold text-slate-900">Comparaciones detalladas y trazabilidad</summary>
                <div class="mt-4 space-y-5">
                    <p class="text-sm text-slate-600">@if ($versionBase)La declaración original preservada, la versión clínica anterior y la versión propuesta son fuentes diferentes. «Se sustituiría» indica lo que dejaría de integrar la nueva versión; el historial permanece conservado.@else La declaración original permanece preservada.@endif</p>
                    @if ($versionBase)
                        <p class="break-words text-sm text-slate-600">Versión clínica anterior {{ $versionBase->numero_version }} · {{ $versionBase->autor_nombre_snapshot }} · {{ $versionBase->especialidad_nombre_snapshot }} · {{ $versionBase->created_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }}).</p>
                    @else
                        <p class="text-slate-600">Sin información clínica previa.</p>
                    @endif
                    @foreach ($secciones as $seccion)
                        @php
                            $bloqueFuente = array_search($seccion['titulo'], $etiquetas, true);
                            $original = $bloqueFuente !== false ? $resultado->dictamenes[$bloqueFuente]['datos_originales'] : null;
                            $hayOriginal = $original && (isset($original['respuesta']) || !empty($original['items']) || (isset($original['valor']) && $original['valor'] !== ''));
                        @endphp
                        @if ($hayOriginal || $seccion['antes'] !== [] || $seccion['despues'] !== [])
                        <section class="min-w-0 space-y-3 border-t border-indigo-100 pt-4" data-comparacion="{{ $seccion['titulo'] }}">
                            <h3 class="font-semibold">{{ $seccion['titulo'] }}</h3>
                            <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr))" data-fuentes-comparacion>
                                @if ($hayOriginal)
                                    <div class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-3" data-fuente="declaracion">
                                        <h4 class="mb-2 text-sm font-semibold text-slate-600">Declaración original</h4>
                                        @if (isset($original['items']))
                                            <p>{{ ($original['respuesta'] ?? null) === 'no' ? 'El paciente declaró No' : (($original['respuesta'] ?? null) === 'si' ? 'El paciente declaró Sí' : 'Sin respuesta original disponible') }}</p>
                                            <ul class="space-y-2">@foreach ($original['items'] as $item)<li class="whitespace-pre-line break-words">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@endforeach</ul>
                                        @else<p class="whitespace-pre-line break-words">{{ $original['valor'] }}</p>@endif
                                    </div>
                                @endif
                                @if ($versionBase && $seccion['antes'] !== [])
                                    <div class="min-w-0 rounded-xl border border-indigo-100 p-3" data-fuente="anterior"><h4 class="mb-2 text-sm font-semibold text-slate-600">Versión clínica anterior</h4><ul class="space-y-2">@foreach ($seccion['antes'] as $texto)<li class="whitespace-pre-line break-words">{{ $texto }}</li>@endforeach</ul></div>
                                @endif
                                <div class="min-w-0 rounded-xl border border-green-100 bg-green-50 p-3" data-fuente="propuesta"><h4 class="mb-2 text-sm font-semibold text-green-900">Versión clínica propuesta</h4><ul class="space-y-2">@forelse ($seccion['despues'] as $texto)<li class="whitespace-pre-line break-words">{{ $texto }}</li>@empty<li>Sin datos propuestos para incorporar.</li>@endforelse</ul></div>
                            </div>
                            @if ($versionBase)
                                @foreach (['conservado' => 'Se conservaría', 'incorporado' => 'Se incorporaría', 'sustituido' => 'Se sustituiría'] as $clave => $titulo)
                                    @if ($seccion[$clave] !== [])<div class="space-y-1"><h4 class="text-sm font-semibold text-indigo-800">{{ $titulo }}</h4><ul class="space-y-1">@foreach ($seccion[$clave] as $texto)<li class="whitespace-pre-line break-words">{{ $texto }}</li>@endforeach</ul></div>@endif
                                @endforeach
                            @endif
                        </section>
                        @endif
                    @endforeach
        <section class="space-y-4 border-t border-indigo-100 pt-4">
            <h3 class="text-lg font-semibold">Declaración original, dictámenes y justificaciones</h3>
            @foreach ($resultado->dictamenes as $bloque => $dictamen)
                <details class="min-w-0 rounded-xl border border-indigo-100 p-3" data-dictamen-detalle="{{ $bloque }}">
                    <summary class="min-h-11 cursor-pointer break-words font-semibold">{{ $etiquetas[$bloque] }} — {{ $etiquetasDictamen[$dictamen['dictamen']] }}</summary>
                    <div class="mt-2 space-y-2 break-words">
                    <p class="text-sm">Origen: {{ $dictamen['procedencias']['origen'] === 'CORRECCION_PROFESIONAL' ? 'Corrección profesional de la declaración' : 'Declaración del paciente' }}.</p>
                    <h4 class="font-semibold">Declaración original conservada</h4>
                    @if (isset($dictamen['datos_originales']['items']))
                        @php($respuestaOriginal = $dictamen['datos_originales']['respuesta'] ?? null)
                        <p>{{ $respuestaOriginal === 'no' ? 'El paciente declaró No' : ($respuestaOriginal === 'si' ? 'El paciente declaró Sí' : 'Sin respuesta original disponible') }}</p>
                        @if ($respuestaOriginal === 'no' && $dictamen['dictamen'] === 'VALIDAR')<p class="text-sm">La negativa se admite como declaración del paciente. No genera automáticamente «No presenta»; el resultado clínico depende del relevamiento profesional.</p>@endif
                        @if ($dictamen['datos_originales']['items'] !== [] || $respuestaOriginal !== 'no')
                            <ul>@forelse ($dictamen['datos_originales']['items'] as $item)<li class="whitespace-pre-line">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@empty<li>Sin información declarada; no acredita ausencia.</li>@endforelse</ul>
                        @endif
                    @else
                        <p class="whitespace-pre-line">{{ $dictamen['datos_originales']['valor'] ?? 'Sin información declarada' }}</p>
                    @endif
                    @if ($dictamen['correccion_profesional'] !== null)
                        <h4 class="font-semibold">Corrección profesional propuesta</h4>
                        @if (isset($dictamen['correccion_profesional']['items']))
                            <ul>@forelse ($dictamen['correccion_profesional']['items'] as $item)<li class="whitespace-pre-line">{{ $item['descripcion'] }} · {{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS[$item['tipo_destino']] }} · {{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$item['procedencia']] }} · {{ isset($item['item_origen_id']) ? 'Ítem original #'.$item['item_origen_id'] : 'Nuevo aporte profesional' }}</li>@empty<li>Sin ítems admitidos en la corrección</li>@endforelse</ul>
                        @else
                            <p class="whitespace-pre-line">{{ $dictamen['correccion_profesional']['valor'] ?? 'Sin dato' }}</p>
                        @endif
                        @if (isset($dictamen['datos_originales']['items']))
                            <h4 class="font-semibold">Tratamiento de los ítems originales</h4>
                            <ul>@foreach ($dictamen['datos_originales']['items'] as $original)
                                <li>Ítem #{{ $original['item_origen_id'] }}: {{ $original['descripcion'] }} — {{ collect($dictamen['correccion_profesional']['items'])->contains('item_origen_id', $original['item_origen_id']) ? 'Sustituido por su corrección profesional en el resultado' : 'Excluido del resultado corregido' }}. El original permanece en la declaración.</li>
                            @endforeach</ul>
                        @else
                            <p class="text-sm">El valor declarado se sustituye por la corrección en el resultado; el original permanece en la declaración.</p>
                        @endif
                    @endif
                    @if ($dictamen['observacion_profesional'])<p class="whitespace-pre-line">Justificación: {{ $dictamen['observacion_profesional'] }}</p>@endif
                    @if (in_array($dictamen['dictamen'], ['OBSERVAR', 'DESCARTAR'], true))<p class="font-semibold text-amber-900">Dato declarado excluido de la incorporación.@if ($versionBase) Los datos clínicos previos se conservan salvo sustitución explícita.@endif</p>@endif
                    </div>
                </details>
            @endforeach
        </section>
                </div>
            </details>
        <section id="previa-confirmacion" aria-labelledby="previa-confirmacion-titulo" class="space-y-4 rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm sm:p-5">
        <h2 id="previa-confirmacion-titulo" class="rounded-xl bg-indigo-50 px-3 py-2 text-lg font-semibold text-slate-900">Confirmación profesional</h2>
        <form method="POST" action="{{ route('historia-clinica.revision.confirmar', ['turno' => $turno->id, 'declaracion' => $declaracion->id]) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="confirmacion" value="{{ $comprobante }}">
            <p x-cloak x-show="propuestaEditada" role="alert" class="font-semibold text-amber-900">Editaste la propuesta. Generá una nueva vista previa antes de confirmar.</p>
            <fieldset :disabled="propuestaEditada" class="space-y-4">
            <label class="flex items-start gap-3"><input type="checkbox" name="confirmar" value="1" required class="mt-1"> Confirmo con mi autoría profesional la revisión de esta declaración y la incorporación de los datos de esta vista previa al expediente de este paciente.</label>
            <p class="text-sm">Si querés cambiar un dato o dictamen, editá la propuesta y generá una nueva vista previa antes de confirmar.</p>
            <button type="submit" class="min-h-11 w-full rounded-xl bg-indigo-700 px-5 py-3 font-semibold text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto">Confirmar revisión e incorporar datos</button>
            </fieldset>
        </form>
        <details class="min-w-0 rounded-xl border border-indigo-200 p-3" @input="propuestaEditada = true" @change="propuestaEditada = true" data-retorno-edicion>
            <summary class="min-h-11 cursor-pointer font-semibold text-indigo-700">Volver a editar esta propuesta</summary>
            @include('historia-clinica.revision-formulario')
        </details>
        <a href="{{ route('historia-clinica.desde-turno', $turno->id) }}" class="inline-flex min-h-11 items-center font-semibold text-indigo-700 underline">Salir sin incorporar y volver al expediente</a>
        </section>
        </div>
        <aside aria-labelledby="previa-resumen-titulo" class="min-w-0 space-y-5 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm xl:sticky xl:top-6">
            <h2 id="previa-resumen-titulo" class="text-xl font-semibold text-slate-900">Resumen de la propuesta</h2>
            <dl class="divide-y divide-indigo-100 rounded-xl bg-indigo-50 px-3" aria-label="Dictámenes profesionales">
                @foreach (['VALIDAR' => ['Validados', '✓', 'text-green-700'], 'CORREGIR' => ['Corregidos', '✎', 'text-amber-700'], 'OBSERVAR' => ['Observados', '◉', 'text-indigo-700'], 'DESCARTAR' => ['Descartados', '×', 'text-rose-700']] as $codigo => [$etiqueta, $icono, $color])
                    <div class="flex min-h-11 items-center justify-between gap-3 py-2" data-resumen-dictamen="{{ $codigo }}"><dt class="flex items-center gap-2"><span aria-hidden="true" class="text-xl {{ $color }}">{{ $icono }}</span>{{ $etiqueta }}</dt><dd class="font-semibold" data-cantidad>{{ $conteos[$codigo] }}</dd></div>
                @endforeach
            </dl>
            <p class="text-sm text-slate-600">Estos contadores corresponden a dictámenes. Los relevamientos complementarios y el núcleo familiar figuran en el resultado clínico.</p>
            <nav aria-label="Secciones de la propuesta" class="space-y-2 border-t border-indigo-100 pt-4">
                <h3 class="font-semibold text-slate-900">Ir a sección</h3>
                @foreach (['resultado' => 'Resultado clínico', 'comparaciones' => 'Comparaciones detalladas', 'excluidos' => 'Datos no incorporados', 'confirmacion' => 'Confirmación profesional'] as $destino => $titulo)
                    <a href="#previa-{{ $destino }}" class="flex min-h-11 items-center justify-between gap-2 border-b border-indigo-50 py-2 font-medium text-indigo-700 underline">{{ $titulo }}<span aria-hidden="true">→</span></a>
                @endforeach
            </nav>
            <p class="rounded-xl bg-indigo-50 p-3 text-sm text-indigo-950">La declaración original se conserva siempre. Esta propuesta sólo se aplica al expediente después de tu confirmación explícita.</p>
        </aside>
        </div>
    </section>
</x-app-layout>
