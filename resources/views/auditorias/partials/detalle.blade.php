@php
    $valoresAnteriores = $auditoria->valores_anteriores ?? [];
    $valoresNuevos = $auditoria->valores_nuevos ?? [];
    $metadata = $auditoria->metadata ?? [];

    $camposModificados = collect(array_keys($valoresAnteriores))
        ->merge(array_keys($valoresNuevos))
        ->unique()
        ->values();

    $formatearCampo = static function (string $campo): string {
        return ucfirst(str_replace('_', ' ', $campo));
    };

    $formatearValor = static function (mixed $valor): string {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_array($valor)) {
            return json_encode(
                $valor,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '—';
        }

        return (string) $valor;
    };

    $tipoRegistro = $auditoria->auditable_type
        ? class_basename($auditoria->auditable_type)
        : null;

    $tipoRegistro = match ($tipoRegistro) {
        'User' => 'Usuario',
        'Paciente' => 'Paciente',
        'Kinesiologa' => 'Kinesióloga',
        'MatriculaProfesionalKinesiologia' => 'Matrícula profesional',
        'Jurisdiccion' => 'Jurisdicción',
        'TituloProfesional' => 'Título profesional',
        'ColegioEmisor' => 'Colegio emisor',
        'EspecialidadKinesiologia' => 'Especialidad de kinesiología',
        null => null,
        default => $tipoRegistro,
    };

    $registroAfectado = $tipoRegistro && $auditoria->auditable_id
        ? $tipoRegistro . ' #' . $auditoria->auditable_id
        : 'No aplica';

    $eventoTexto = ucfirst(
        str_replace('_', ' ', (string) $auditoria->evento)
    );
@endphp

<x-modal
    name="auditoria-detalle-{{ $auditoria->id }}"
    maxWidth="2xl"
>
    <div class="p-6">
        {{-- Encabezado --}}
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-700"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        Detalle de trazabilidad
                    </h3>

                    <p class="mt-0.5 text-sm text-gray-500">
                        Registro de auditoría #{{ $auditoria->id }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                x-on:click="$dispatch('close')"
                class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                aria-label="Cerrar"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </button>
        </div>

        {{-- Información principal --}}
        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Ejecutado por
                </p>

                <p class="mt-1 text-sm font-semibold text-gray-900">
                    {{ $auditoria->actor_nombre ?? 'Sistema' }}
                </p>

                <p class="mt-0.5 text-xs text-gray-500">
                    {{ $auditoria->actor_email ?? 'Sin usuario autenticado' }}
                </p>
            </div>

            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Fecha / Hora
                </p>

                <p class="mt-1 text-sm font-semibold text-gray-900">
                    {{ $auditoria->created_at?->format('d/m/Y H:i:s') ?? '—' }}
                </p>
            </div>

            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Evento
                </p>

                <span
                    class="mt-2 inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800"
                >
                    {{ $eventoTexto }}
                </span>
            </div>

            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Módulo
                </p>

                <span
                    class="mt-2 inline-flex rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700"
                >
                    {{ $auditoria->modulo }}
                </span>
            </div>

            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    IP de origen
                </p>

                <p class="mt-1 break-all font-mono text-sm text-gray-800">
                    {{ $auditoria->ip_address ?? 'No disponible' }}
                </p>
            </div>

            <div class="rounded-xl bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Registro afectado
                </p>

                <p class="mt-1 font-mono text-sm font-semibold text-gray-800">
                    {{ $registroAfectado }}
                </p>
            </div>
        </div>

        {{-- Cambios --}}
        <div class="mt-6 border-t border-gray-200 pt-6">
            @if ($camposModificados->isNotEmpty())
                <div class="flex items-center gap-2">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-gray-700"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M8 3 4 7l4 4" />
                        <path d="M4 7h16" />
                        <path d="m16 21 4-4-4-4" />
                        <path d="M20 17H4" />
                    </svg>

                    <h4 class="font-semibold text-gray-900">
                        Cambios registrados
                    </h4>
                </div>

                <div class="mt-3 overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Campo
                                </th>

                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Valor anterior
                                </th>

                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Valor nuevo
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($camposModificados as $campo)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-800">
                                        {{ $formatearCampo($campo) }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-block max-w-sm break-words rounded-md bg-red-50 px-2.5 py-1.5 font-mono text-xs text-red-800"
                                        >
                                            {{ $formatearValor($valoresAnteriores[$campo] ?? null) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-block max-w-sm break-words rounded-md bg-emerald-50 px-2.5 py-1.5 font-mono text-xs text-emerald-800"
                                        >
                                            {{ $formatearValor($valoresNuevos[$campo] ?? null) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800"
                >
                    <div class="flex gap-3">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="mt-0.5 h-5 w-5 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 16v-4" />
                            <path d="M12 8h.01" />
                        </svg>

                        <p>
                            Este evento no incluye valores anteriores y posteriores
                            comparables. Se muestra la información contextual disponible.
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Metadata contextual --}}
        @if (! empty($metadata))
            <div class="mt-6 border-t border-gray-200 pt-6">
                <h4 class="font-semibold text-gray-900">
                    Información contextual
                </h4>

                <dl class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach ($metadata as $campo => $valor)
                        <div
                            class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4"
                        >
                            <dt class="text-sm font-semibold text-gray-600">
                                {{ $formatearCampo((string) $campo) }}
                            </dt>

                            <dd class="break-words text-sm text-gray-800 sm:col-span-2">
                                {{ $formatearValor($valor) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        {{-- Contexto técnico --}}
        @if ($auditoria->user_agent)
            <div class="mt-6 border-t border-gray-200 pt-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Agente de usuario
                </p>

                <p class="mt-1 break-all text-xs text-gray-500">
                    {{ $auditoria->user_agent }}
                </p>
            </div>
        @endif

        {{-- Advertencia --}}
        <div class="mt-6 flex gap-2 rounded-xl bg-slate-50 px-4 py-3 text-xs text-gray-500">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 shrink-0"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3v8Z" />
                <path d="m9 12 2 2 4-4" />
            </svg>

            <p>
                Esta información es de solo lectura. No se almacenan contraseñas,
                tokens ni otras credenciales sensibles.
            </p>
        </div>

        {{-- Pie --}}
        <div class="mt-6 flex justify-end">
            <button
                type="button"
                x-on:click="$dispatch('close')"
                class="inline-flex items-center rounded-full bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200"
            >
                Cerrar
            </button>
        </div>
    </div>
</x-modal>
