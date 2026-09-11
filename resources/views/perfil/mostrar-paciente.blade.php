<x-app-layout>
    @php
        $items = $declaracion?->items ?? collect();

        $antecedentes = $items
            ->where(
                'tipo',
                \App\Models\ItemDeclaracionSalud::TIPO_ANTECEDENTE
            )
            ->values();

        $cirugias = $items
            ->where(
                'tipo',
                \App\Models\ItemDeclaracionSalud::TIPO_CIRUGIA
            )
            ->values();

        $alergias = $items
            ->where(
                'tipo',
                \App\Models\ItemDeclaracionSalud::TIPO_ALERGIA
            )
            ->values();

        $medicaciones = $items
            ->where(
                'tipo',
                \App\Models\ItemDeclaracionSalud::TIPO_MEDICACION
            )
            ->values();

        $sexoLabel = match ($user->sexo) {
            'masculino' => 'Masculino',
            'femenino' => 'Femenino',
            'otro' => 'Otro',
            'prefiero_no_decir' => 'Prefiero no decir',
            default => 'No informado',
        };

        $actividadLabel = match (
            $declaracion?->nivel_actividad_fisica
        ) {
            'SEDENTARIO' => 'Sedentario',
            'MODERADO' => 'Moderado',
            'ACTIVO' => 'Activo',
            null => 'No informado',
            default => $declaracion->nivel_actividad_fisica,
        };

        $estadoDeclaracion = match (
            $declaracion?->estado
        ) {
            \App\Models\DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION
                => 'Pendiente de revisión',
            null => 'No informado',
            default => $declaracion->estado,
        };

        $categoriasClinicas = [
            'Antecedentes' => $antecedentes,
            'Cirugías' => $cirugias,
            'Alergias' => $alergias,
            'Medicación habitual' => $medicaciones,
        ];
    @endphp

    <div class="min-h-screen bg-slate-100 py-8 sm:py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            <div
                class="overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-slate-200"
            >
                {{-- Cabecera --}}
                <div
                    class="bg-gradient-to-r from-slate-900 to-indigo-950 px-6 py-7 text-white sm:px-8"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/10"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-6 w-6"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h1 class="text-2xl font-bold tracking-tight">
                                Mi perfil
                            </h1>

                            <p
                                class="mt-1 max-w-2xl text-sm leading-6 text-slate-200"
                            >
                                Consulte sus datos personales y la declaración
                                inicial de salud registrada en KinesioSoft.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-7 p-6 sm:p-8">

                    {{-- Estado general del perfil --}}
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-sm font-bold text-emerald-900"
                            >
                                Perfil clínico inicial completado
                            </p>

                            <p
                                class="mt-1 text-sm text-emerald-800"
                            >
                                Los datos de esta pantalla son de consulta.
                            </p>
                        </div>

                        <span
                            class="inline-flex w-fit rounded-full bg-white px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200"
                        >
                            Perfil completo
                        </span>
                    </div>

                    {{-- Datos personales --}}
                    <section
                        class="overflow-hidden rounded-2xl border border-slate-200"
                    >
                        <div
                            class="border-b border-slate-200 bg-slate-50 px-5 py-4"
                        >
                            <h2 class="font-bold text-slate-900">
                                Datos personales y de contacto
                            </h2>
                        </div>

                        <dl
                            class="grid grid-cols-1 gap-x-8 gap-y-5 p-5 sm:grid-cols-2"
                        >
                            <div>
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    DNI
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    {{ $user->dni ?: 'No informado' }}
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Fecha de nacimiento
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    @if ($user->fecha_nacimiento)
                                        {{
                                            \Illuminate\Support\Carbon::parse(
                                                $user->fecha_nacimiento
                                            )->format('d/m/Y')
                                        }}
                                    @else
                                        No informado
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Sexo
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    {{ $sexoLabel }}
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Teléfono
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    {{ $user->telefono ?: 'No informado' }}
                                </dd>
                            </div>

                            <div class="sm:col-span-2">
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Dirección
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    {{
                                        $paciente?->direccion
                                            ?: 'No informado'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Contacto de emergencia
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    {{
                                        $paciente?->contacto_emergencia_nombre
                                            ?: 'No informado'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Teléfono de emergencia
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-medium text-slate-900"
                                >
                                    {{
                                        $paciente?->contacto_emergencia_telefono
                                            ?: 'No informado'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Declaración inicial --}}
                    <section
                        class="overflow-hidden rounded-2xl border border-slate-200"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h2 class="font-bold text-slate-900">
                                    Declaración inicial de salud
                                </h2>

                                @if ($declaracion)
                                    <p
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        Registrada el
                                        {{
                                            $declaracion
                                                ->created_at
                                                ->format('d/m/Y H:i')
                                        }}
                                    </p>
                                @endif
                            </div>

                            <span
                                class="inline-flex w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800"
                            >
                                {{ $estadoDeclaracion }}
                            </span>
                        </div>

                        @if ($declaracion)
                            <dl
                                class="grid grid-cols-1 gap-x-8 gap-y-5 p-5 sm:grid-cols-2"
                            >
                                <div class="sm:col-span-2">
                                    <dt
                                        class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Objetivo de salud
                                    </dt>

                                    <dd
                                        class="mt-1 whitespace-pre-line text-sm font-medium text-slate-900"
                                    >{{ $declaracion->objetivo_salud }}</dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Peso
                                    </dt>

                                    <dd
                                        class="mt-1 text-sm font-medium text-slate-900"
                                    >
                                        {{ $declaracion->peso_kg }} kg
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Altura
                                    </dt>

                                    <dd
                                        class="mt-1 text-sm font-medium text-slate-900"
                                    >
                                        {{ $declaracion->altura_cm }} cm
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Ocupación
                                    </dt>

                                    <dd
                                        class="mt-1 text-sm font-medium text-slate-900"
                                    >
                                        {{ $declaracion->ocupacion }}
                                    </dd>
                                </div>

                                <div>
                                    <dt
                                        class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Nivel de actividad física
                                    </dt>

                                    <dd
                                        class="mt-1 text-sm font-medium text-slate-900"
                                    >
                                        {{ $actividadLabel }}
                                    </dd>
                                </div>

                                <div class="sm:col-span-2">
                                    <dt
                                        class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Detalle de actividad física
                                    </dt>

                                    <dd
                                        class="mt-1 whitespace-pre-line text-sm font-medium text-slate-900"
                                    >{{ $declaracion->detalle_actividad_fisica }}</dd>
                                </div>
                            </dl>
                        @else
                            <div class="p-5">
                                <p class="text-sm text-slate-600">
                                    No existe una declaración de salud
                                    registrada.
                                </p>
                            </div>
                        @endif
                    </section>

                    {{-- Información clínica declarada --}}
                    @if ($declaracion)
                        <section
                            class="overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <div
                                class="border-b border-slate-200 bg-slate-50 px-5 py-4"
                            >
                                <h2 class="font-bold text-slate-900">
                                    Información de salud declarada
                                </h2>
                            </div>

                            <div class="divide-y divide-slate-200">
                                @foreach (
                                    $categoriasClinicas
                                    as $titulo => $coleccion
                                )
                                    <div class="p-5">
                                        <div
                                            class="flex items-center justify-between gap-4"
                                        >
                                            <h3
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{ $titulo }}
                                            </h3>

                                            @if ($coleccion->isNotEmpty())
                                                <span
                                                    class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800"
                                                >
                                                    Sí
                                                </span>
                                            @else
                                                <span
                                                    class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800"
                                                >
                                                    No
                                                </span>
                                            @endif
                                        </div>

                                        @if ($coleccion->isNotEmpty())
                                            <ul
                                                class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700"
                                            >
                                                @foreach ($coleccion as $item)
                                                    <li>
                                                        {{ $item->descripcion }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Nota clínica --}}
                    <div
                        class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5"
                    >
                        <div class="flex gap-3">
                            <div
                                class="mt-0.5 shrink-0 text-indigo-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-5 w-5"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m0 3.75h.008v.008H12V16.5zm9-4.5a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-sm font-bold text-indigo-950"
                                >
                                    Información declarada por el paciente
                                </p>

                                <p
                                    class="mt-1 text-sm leading-6 text-indigo-800"
                                >
                                    La declaración de salud permanece
                                    separada de la Historia Clínica oficial
                                    y está sujeta a revisión profesional.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Navegación --}}
                    <div
                        class="flex justify-end border-t border-slate-200 pt-6"
                    >
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Volver al dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
