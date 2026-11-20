<article class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" aria-labelledby="version-base">
    <h2 id="version-base" class="text-lg font-semibold">Datos de base disponibles — versión {{ $version->numero_version }}</h2>
    <p class="text-sm">{{ $version->autor_nombre_snapshot }} · {{ $version->especialidad_nombre_snapshot }} · {{ $version->created_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }})</p>
    <p class="text-sm">Carga profesional directa. Procedencia del relevamiento: {{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$version->procedencia_relevamiento] ?? $version->procedencia_relevamiento }}.</p>
    @foreach (\App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $titulo)
        <section class="space-y-2 border-t border-gray-200 pt-3">
            <h3 class="font-semibold">{{ $titulo }}</h3>
            <p>{{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::RELEVAMIENTOS[$version->relevamientos[$tipo]] ?? $version->relevamientos[$tipo] }}</p>
            <ul class="space-y-2">
                @foreach ($version->items->where('tipo', $tipo) as $item)
                    <li><p class="whitespace-pre-line">{{ $item->descripcion }}</p><p class="text-sm text-gray-600">{{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::PROCEDENCIAS[$item->procedencia] ?? $item->procedencia }} · Pendiente de codificación</p></li>
                @endforeach
            </ul>
        </section>
    @endforeach
    <section class="space-y-2 border-t border-gray-200 pt-3">
        <h3 class="font-semibold">Núcleo familiar</h3>
        <p>{{ \App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica::FAMILIA[$version->resultado_nucleo_familiar] ?? $version->resultado_nucleo_familiar }}</p>
        @if ($version->motivo_nucleo_familiar)<p>{{ $version->motivo_nucleo_familiar }}</p>@endif
        <ul class="space-y-2">
            @foreach ($version->integrantes as $integrante)
                <li>{{ $integrante->nombre_apellido ?? 'Nombre no disponible' }} · {{ $integrante->vinculo }} @if ($integrante->contacto_emergencia_confirmado) · Contacto de emergencia confirmado como integrante @endif</li>
            @endforeach
        </ul>
    </section>
    <section class="space-y-2 border-t border-gray-200 pt-3">
        <h3 class="font-semibold">Datos complementarios</h3>
        <dl class="space-y-2">
            @foreach (['objetivo_salud' => 'Objetivo de salud', 'peso_kg' => 'Peso (kg)', 'altura_cm' => 'Altura (cm)', 'ocupacion' => 'Ocupación', 'nivel_actividad_fisica' => 'Actividad física', 'detalle_actividad_fisica' => 'Detalle de actividad física'] as $campo => $etiqueta)
                <div><dt>{{ $etiqueta }}</dt><dd>{{ $version->$campo ?? 'Sin informar' }}</dd></div>
            @endforeach
        </dl>
    </section>
</article>
