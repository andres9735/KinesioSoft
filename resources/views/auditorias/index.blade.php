<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                        <path d="M12 8v4" />
                        <path d="M12 16h.01" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900">
                        Auditoría
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Consulta y trazabilidad de acciones del sistema.
                    </p>
                </div>
            </div>

            @php
                $rolActual = auth()->user()->esSuperadmin()
                    ? 'Superadministrador'
                    : 'Administrador';
            @endphp

            <span
                class="inline-flex w-fit items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="m16 11 2 2 4-4" />
                </svg>

                {{ $rolActual }}
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filtros --}}
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="p-6">
                    <form
                        method="GET"
                        action="{{ route('auditorias.index') }}"
                        class="space-y-5"
                    >
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <div>
                                <x-input-label
                                    for="fecha_desde"
                                    :value="__('Fecha desde')"
                                />

                                <input
                                    id="fecha_desde"
                                    name="fecha_desde"
                                    type="date"
                                    value="{{ request('fecha_desde') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>

                            <div>
                                <x-input-label
                                    for="fecha_hasta"
                                    :value="__('Fecha hasta')"
                                />

                                <input
                                    id="fecha_hasta"
                                    name="fecha_hasta"
                                    type="date"
                                    value="{{ request('fecha_hasta') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>

                            <div>
                                <x-input-label
                                    for="evento"
                                    :value="__('Evento')"
                                />

                                <select
                                    id="evento"
                                    name="evento"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>

                                    @foreach ($eventos as $evento)
                                        <option
                                            value="{{ $evento }}"
                                            @selected(request('evento') === $evento)
                                        >
                                            {{ ucfirst(str_replace('_', ' ', $evento)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label
                                    for="modulo"
                                    :value="__('Módulo')"
                                />

                                <select
                                    id="modulo"
                                    name="modulo"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>

                                    @foreach ($modulos as $modulo)
                                        <option
                                            value="{{ $modulo }}"
                                            @selected(request('modulo') === $modulo)
                                        >
                                            {{ $modulo }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label
                                    for="actor"
                                    :value="__('Ejecutado por')"
                                />

                                <input
                                    id="actor"
                                    name="actor"
                                    type="text"
                                    value="{{ request('actor') }}"
                                    placeholder="Usuario o email"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                        </div>

                        @if ($errors->any())
                            <div
                                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                                role="alert"
                            >
                                @foreach ($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
                                </svg>

                                Filtrar
                            </button>

                            <a
                                href="{{ route('auditorias.index') }}"
                                class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="m7 21-4-4 9-9 4 4-9 9Z" />
                                    <path d="m14 6 4 4" />
                                    <path d="M17 3 21 7" />
                                </svg>

                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            {{-- Listado --}}
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-col gap-3 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-gray-700"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <rect width="18" height="18" x="3" y="3" rx="2" />
                            <path d="M3 9h18" />
                            <path d="M3 15h18" />
                            <path d="M9 3v18" />
                        </svg>

                        <h3 class="text-lg font-semibold text-gray-900">
                            Registros de auditoría
                        </h3>
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600"
                        >
                            {{ $auditorias->total() }}
                            {{ $auditorias->total() === 1 ? 'registro' : 'registros' }}
                        </span>

                        @if ($auditorias->total() > 0)
                            <form
                                method="POST"
                                action="{{ route('auditorias.exportar-pdf') }}"
                                class="inline-flex"
                            >
                                @csrf

                                @foreach (
                                    [
                                        'fecha_desde',
                                        'fecha_hasta',
                                        'evento',
                                        'modulo',
                                        'actor',
                                    ] as $filtro
                                )
                                    @if (request()->filled($filtro))
                                        <input
                                            type="hidden"
                                            name="{{ $filtro }}"
                                            value="{{ request($filtro) }}"
                                        >
                                    @endif
                                @endforeach

                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <path d="M14 2v6h6" />
                                        <path d="M9 15h6" />
                                        <path d="M9 11h1" />
                                        <path d="M9 19h6" />
                                    </svg>

                                    Exportar a PDF
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Fecha / Hora
                                </th>

                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Ejecutado por
                                </th>

                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Evento
                                </th>

                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Módulo
                                </th>

                                <th
                                    scope="col"
                                    class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-600"
                                >
                                    Acción
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($auditorias as $auditoria)
                                @php
                                    $evento = (string) $auditoria->evento;

                                    $eventoTexto = ucfirst(
                                        str_replace('_', ' ', $evento)
                                    );

                                    $eventoClase = match (true) {
                                        str_contains($evento, 'creado'),
                                        str_contains($evento, 'creada'),
                                        str_contains($evento, 'activado'),
                                        str_contains($evento, 'activada')
                                            => 'bg-emerald-100 text-emerald-800',

                                        str_contains($evento, 'actualizado'),
                                        str_contains($evento, 'actualizada'),
                                        str_contains($evento, 'cambiado'),
                                        str_contains($evento, 'cambiada'),
                                        str_contains($evento, 'completado'),
                                        str_contains($evento, 'completada')
                                            => 'bg-amber-100 text-amber-800',

                                        str_contains($evento, 'inactivado'),
                                        str_contains($evento, 'inactivada')
                                            => 'bg-red-100 text-red-800',

                                        str_contains($evento, 'inicio_sesion')
                                            => 'bg-cyan-100 text-cyan-800',

                                        str_contains($evento, 'cierre_sesion')
                                            => 'bg-gray-200 text-gray-700',

                                        str_contains($evento, 'verificado'),
                                        str_contains($evento, 'restablecida'),
                                        str_contains($evento, 'contrasena')
                                            => 'bg-blue-100 text-blue-800',

                                        str_contains($evento, 'export')
                                            => 'bg-indigo-100 text-indigo-800',

                                        default
                                            => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp

                                <tr class="transition hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $auditoria->created_at?->format('d/m/Y') ?? '—' }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ $auditoria->created_at?->format('H:i:s') ?? '—' }}
                                        </p>
                                    </td>

                                    <td class="px-6 py-4">
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $auditoria->actor_nombre ?? 'Sistema' }}
                                        </p>

                                        @if ($auditoria->actor_email)
                                            <p class="mt-0.5 text-xs text-gray-500">
                                                {{ $auditoria->actor_email }}
                                            </p>
                                        @else
                                            <p class="mt-0.5 text-xs text-gray-400">
                                                Sin usuario autenticado
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $eventoClase }}"
                                        >
                                            {{ $eventoTexto }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                        >
                                            {{ $auditoria->modulo }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-center">
                                        @can('view', $auditoria)
                                            <button
                                                type="button"
                                                x-data
                                                x-on:click.prevent="$dispatch(
                                                    'open-modal',
                                                    'auditoria-detalle-{{ $auditoria->id }}'
                                                )"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-blue-300 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>

                                                Ver
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                @php
                                    $hayFiltrosActivos =
                                        request()->filled('fecha_desde') ||
                                        request()->filled('fecha_hasta') ||
                                        request()->filled('evento') ||
                                        request()->filled('modulo') ||
                                        request()->filled('actor');
                                @endphp

                                <tr>
                                    <td
                                        colspan="5"
                                        class="px-6 py-12"
                                    >
                                        <div class="mx-auto max-w-lg rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="mx-auto h-12 w-12 text-gray-400"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                            >
                                                <path d="M21 8v13H3V8" />
                                                <path d="M1 3h22v5H1z" />
                                                <path d="M10 12h4" />
                                            </svg>

                                            @if ($hayFiltrosActivos)
                                                <h4 class="mt-4 text-base font-semibold text-gray-900">
                                                    No se encontraron registros
                                                </h4>

                                                <p class="mt-2 text-sm text-gray-500">
                                                    No hay registros de auditoría que coincidan con los filtros aplicados.
                                                </p>

                                                <div class="mt-6">
                                                    <a
                                                        href="{{ route('auditorias.index') }}"
                                                        class="inline-flex items-center justify-center rounded-xl bg-gray-200 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-gray-800 transition hover:bg-gray-300"
                                                    >
                                                        Limpiar filtros
                                                    </a>
                                                </div>
                                            @else
                                                <h4 class="mt-4 text-base font-semibold text-gray-900">
                                                    Aún no hay registros de auditoría
                                                </h4>

                                                <p class="mt-2 text-sm text-gray-500">
                                                    Los eventos auditables aparecerán aquí a medida que se registren acciones en el sistema.
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 bg-gray-50/60 px-6 py-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1.5">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 16v-4" />
                                    <path d="M12 8h.01" />
                                </svg>

                                Registros inmutables
                            </span>

                            <span class="inline-flex items-center gap-1.5">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3v8Z" />
                                    <path d="m9 12 2 2 4-4" />
                                </svg>

                                No se almacenan credenciales sensibles
                            </span>
                        </div>

                        @if ($auditorias->hasPages())
                            <div>
                                {{ $auditorias->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            @foreach ($auditorias as $auditoria)
                @can('view', $auditoria)
                    @include(
                        'auditorias.partials.detalle',
                        ['auditoria' => $auditoria]
                    )
                @endcan
            @endforeach
        </div>
    </div>
</x-app-layout>
