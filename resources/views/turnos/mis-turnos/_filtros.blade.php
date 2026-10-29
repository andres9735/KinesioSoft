@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $valorFiltro = static function (string $campo) use ($filtros): string {
        $valor = old($campo, $filtros[$campo] ?? '');
        return is_string($valor) ? $valor : '';
    };
    $opcionesTipo = [
        \App\Enums\TipoTurnoEnum::PRIMERA_CONSULTA->value => 'Primera consulta',
        \App\Enums\TipoTurnoEnum::SEGUIMIENTO->value => 'Seguimiento',
    ];
    $opcionesEstado = [
        \App\Enums\EstadoTurnoEnum::PENDIENTE->value => 'Pendiente',
        \App\Enums\EstadoTurnoEnum::CONFIRMADO->value => 'Confirmado',
        \App\Enums\EstadoTurnoEnum::EN_ATENCION->value => 'En atención',
        \App\Enums\EstadoTurnoEnum::ATENDIDO->value => 'Atendido',
        \App\Enums\EstadoTurnoEnum::AUSENTE->value => 'Ausente',
        \App\Enums\EstadoTurnoEnum::CANCELADO->value => 'Cancelado',
    ];
@endphp

<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100 sm:p-6" aria-labelledby="filtros-titulo">
    <h2 id="filtros-titulo" class="text-lg font-semibold text-gray-900">Buscar y filtrar turnos</h2>
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-700" role="alert">
            <p class="font-semibold">Revisá los criterios de búsqueda.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="GET" action="{{ route('turnos.mis-turnos.index') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-labelledby="filtros-titulo">
        <div class="min-w-0">
            <x-input-label for="profesional" value="Buscar profesional" />
            <x-text-input id="profesional" name="profesional" type="search" maxlength="255" :value="$valorFiltro('profesional')" class="mt-1 min-h-11 w-full" aria-describedby="profesional-ayuda profesional-error" :aria-invalid="$errors->has('profesional') ? 'true' : 'false'" />
            <p id="profesional-ayuda" class="mt-1 text-xs text-gray-500">Nombre o apellido de tu profesional.</p>
            <div id="profesional-error"><x-input-error :messages="$errors->get('profesional')" /></div>
        </div>
        @foreach (['tipo' => ['Tipo de turno', $opcionesTipo], 'estado' => ['Estado', $opcionesEstado]] as $campo => [$etiqueta, $opciones])
            <div class="min-w-0">
                <x-input-label :for="$campo" :value="$etiqueta" />
                <select id="{{ $campo }}" name="{{ $campo }}" class="mt-1 min-h-11 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="{{ $campo }}-error" aria-invalid="{{ $errors->has($campo) ? 'true' : 'false' }}">
                    <option value="">Todos</option>
                    @foreach ($opciones as $valor => $texto)
                        <option value="{{ $valor }}" @selected($valorFiltro($campo) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                <div id="{{ $campo }}-error"><x-input-error :messages="$errors->get($campo)" /></div>
            </div>
        @endforeach
        @foreach (['fecha_desde' => 'Fecha desde', 'fecha_hasta' => 'Fecha hasta'] as $campo => $etiqueta)
            <div class="min-w-0">
                <x-input-label :for="$campo" :value="$etiqueta" />
                <x-text-input :id="$campo" :name="$campo" type="date" :value="$valorFiltro($campo)" class="mt-1 min-h-11 w-full" :aria-describedby="$campo.'-error'" :aria-invalid="$errors->has($campo) ? 'true' : 'false'" />
                <div id="{{ $campo }}-error"><x-input-error :messages="$errors->get($campo)" /></div>
            </div>
        @endforeach
        <div class="flex flex-col justify-end gap-2 sm:flex-row sm:items-end">
            <button type="submit" name="aplicar" value="1" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Aplicar filtros</button>
            <a href="{{ route('turnos.mis-turnos.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Limpiar filtros</a>
        </div>
    </form>
</section>
