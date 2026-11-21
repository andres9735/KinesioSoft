<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold text-gray-900">Vista previa de incorporación</h1></x-slot>
    <section class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8" x-data="{ propuestaEditada: false }">
        <div role="status" class="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <h2 class="text-xl font-semibold">Propuesta sin confirmar — no se guardaron datos clínicos</h2>
            <p>La declaración sigue Pendiente. Revisá este resultado antes de confirmar su incorporación.</p>
        </div>
        <div class="space-y-2 rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">{{ trim($paciente->user->name.' '.$paciente->user->apellido) }}</h2>
            <p>Declaración #{{ $declaracion->id }} del {{ $declaracion->created_at->format('d/m/Y H:i') }}. Turno del {{ $turno->inicio_at->format('d/m/Y H:i') }}.</p>
            <p>Profesional: {{ trim($turno->kinesiologa->user->name.' '.$turno->kinesiologa->user->apellido) }} · {{ $especialidad->nombre }}.</p>
            <p>Origen de la futura versión: <strong>Revisión de declaración de salud</strong>.</p>
            <p>{{ $historia ? 'Expediente existente: '.$historia->nro_historia_clinica : 'Se requerirá crear una única historia clínica activa' }}. Versión propuesta: {{ ($versionBase?->numero_version ?? 0) + 1 }}.</p>
            <p>Procedencia del relevamiento complementario: {{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$resultado->datosClinicos['procedencia_relevamiento']] }}.</p>
        </div>
        <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Resultado clínico propuesto y comparación con la versión previa</h2>
            <p>Se muestra el resultado completo. «Sustituido» indica lo que dejaría de integrar la nueva versión; la versión clínica anterior permanece conservada.</p>
            @foreach ($secciones as $seccion)
                <section class="space-y-3 border-t border-gray-200 pt-4">
                    <h3 class="text-lg font-semibold">{{ $seccion['titulo'] }}</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['antes' => 'Versión previa', 'despues' => 'Resultado completo propuesto', 'conservado' => 'Se conservaría', 'incorporado' => 'Se incorporaría', 'sustituido' => 'Se sustituiría'] as $clave => $titulo)
                            <div class="rounded-xl border border-gray-200 p-4"><h4 class="font-semibold">{{ $titulo }}</h4><ul class="mt-2 space-y-2">@forelse ($seccion[$clave] as $texto)<li class="whitespace-pre-line">{{ $texto }}</li>@empty<li class="text-sm text-gray-600">Sin datos en este apartado</li>@endforelse</ul></div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </section>
        <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold">Dictámenes, correcciones y datos excluidos</h2>
            @foreach ($resultado->dictamenes as $bloque => $dictamen)
                <section class="space-y-2 border-t border-gray-200 pt-4">
                    <h3 class="font-semibold">{{ \App\Services\HistoriaClinica\ConsultarRevisionDeclaracion::ETIQUETAS[$bloque] }} — {{ ['VALIDAR' => 'Validar', 'CORREGIR' => 'Corregir', 'OBSERVAR' => 'Observar', 'DESCARTAR' => 'Descartar'][$dictamen['dictamen']] }}</h3>
                    <p class="text-sm">Origen: {{ $dictamen['procedencias']['origen'] === 'CORRECCION_PROFESIONAL' ? 'Corrección profesional de la declaración' : 'Declaración del paciente' }}.</p>
                    <h4 class="font-semibold">Declaración original conservada</h4>
                    @if (isset($dictamen['datos_originales']['items']))
                        <ul>@forelse ($dictamen['datos_originales']['items'] as $item)<li class="whitespace-pre-line">Ítem #{{ $item['item_origen_id'] }}: {{ $item['descripcion'] }}</li>@empty<li>Sin ítems declarados; no acredita ausencia</li>@endforelse</ul>
                    @else
                        <p class="whitespace-pre-line">{{ $dictamen['datos_originales']['valor'] ?? 'Sin dato declarado' }}</p>
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
                    @if (in_array($dictamen['dictamen'], ['OBSERVAR', 'DESCARTAR'], true))<p class="font-semibold text-amber-900">Dato declarado excluido de la incorporación. Los datos clínicos previos se conservan salvo sustitución explícita.</p>@endif
                </section>
            @endforeach
        </section>
        <form method="POST" action="{{ route('historia-clinica.revision.confirmar', ['turno' => $turno->id, 'declaracion' => $declaracion->id]) }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="confirmacion" value="{{ $comprobante }}">
            <p x-show="propuestaEditada" role="alert" class="font-semibold text-amber-900">Editaste la propuesta. Generá una nueva vista previa antes de confirmar.</p>
            <fieldset :disabled="propuestaEditada" class="space-y-4">
            <label class="flex items-start gap-3"><input type="checkbox" name="confirmar" value="1" required class="mt-1"> Confirmo con mi autoría profesional la revisión de esta declaración y la incorporación de los datos de esta vista previa al expediente de este paciente.</label>
            <p class="text-sm">Si querés cambiar un dato o dictamen, editá la propuesta y generá una nueva vista previa antes de confirmar.</p>
            <button type="submit" class="min-h-11 rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Confirmar revisión e incorporar datos</button>
            </fieldset>
        </form>
        <details class="rounded-2xl bg-white p-4 shadow-sm" @input="propuestaEditada = true" @change="propuestaEditada = true">
            <summary class="cursor-pointer text-lg font-semibold">Volver a editar esta propuesta</summary>
            @include('historia-clinica.revision-formulario')
        </details>
        <a href="{{ route('historia-clinica.desde-turno', $turno->id) }}" class="inline-flex min-h-11 items-center font-semibold text-indigo-700 underline">Salir sin incorporar y volver al expediente</a>
    </section>
</x-app-layout>
