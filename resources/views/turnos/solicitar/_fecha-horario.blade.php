<section x-show="paso === 1" aria-labelledby="titulo-paso-1" class="space-y-6">
    <div class="space-y-2">
        <h2 id="titulo-paso-1" x-ref="tituloPaso1" tabindex="-1" class="rounded-lg text-2xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Fecha y horario</h2>
        <p class="text-sm leading-6 text-gray-600">Seleccioná una fecha disponible y luego un horario de inicio.</p>
    </div>
    <p x-show="erroresServidor.inicio_at" x-cloak x-ref="errorInicio" tabindex="-1" x-text="erroresServidor.inicio_at" role="alert" class="rounded-lg text-sm text-red-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"></p>

    <div :aria-busy="estadoDisponibilidad === 'loading'" class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <div class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 id="calendario-titulo" class="text-lg font-semibold capitalize text-gray-900" x-text="mesCalendario"></h3>
                <div class="flex gap-2">
                    <button type="button" @click="cambiarVentana(-1)" :disabled="ventana.desde === @js($configuracionWizard['fechaInicial'])" aria-label="Semana anterior" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-indigo-50 px-3 text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                        <span aria-hidden="true">‹</span><span class="sr-only">Semana anterior</span>
                    </button>
                    <button type="button" @click="cambiarVentana(1)" aria-label="Semana siguiente" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-indigo-50 px-3 text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <span aria-hidden="true">›</span><span class="sr-only">Semana siguiente</span>
                    </button>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Período: <span x-text="fechaLegible(ventana.desde)"></span> — <span x-text="fechaLegible(ventana.hasta)"></span></p>
            <fieldset class="mt-5 min-w-0" aria-describedby="calendario-ayuda">
                <legend class="sr-only">Fecha</legend>
                <div class="mb-2 grid grid-cols-7 text-center text-xs font-medium text-gray-600" aria-hidden="true">
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dia)
                        <span>{{ $dia }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1" aria-labelledby="calendario-titulo">
                    <template x-for="dia in fechasCalendario" :key="dia.fecha">
                        <button type="button" @click="seleccionarFecha(dia.fecha)" :disabled="!['disponible', 'seleccionada'].includes(dia.estado)" :aria-pressed="fechaSeleccionada === dia.fecha" :aria-label="etiquetaFecha(dia.fecha)" :title="etiquetaFecha(dia.fecha)" class="relative flex min-h-11 min-w-0 items-center justify-center rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed" :class="{
                            'border-indigo-600 bg-indigo-600 font-semibold text-white': dia.estado === 'seleccionada',
                            'border-transparent bg-indigo-50 font-semibold text-gray-900 hover:border-indigo-300': dia.estado === 'disponible',
                            'border-transparent bg-white text-gray-400': dia.estado === 'sin_disponibilidad' || dia.estado === 'pasada',
                            'border-dashed border-gray-200 bg-white text-gray-500': dia.estado === 'no_consultada'
                        }">
                            <span x-text="dia.dia"></span>
                            <span x-show="dia.estado === 'seleccionada'" aria-hidden="true" class="absolute bottom-0 text-[9px]">✓</span>
                        </button>
                    </template>
                </div>
            </fieldset>
            <ul class="mt-5 flex flex-wrap gap-x-3 gap-y-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-600" aria-label="Referencias de fechas">
                <li><span aria-hidden="true" class="mr-1 inline-block h-2 w-2 rounded-full bg-indigo-100"></span>Disponible</li>
                <li><span aria-hidden="true" class="mr-1 inline-block h-2 w-2 rounded-full bg-gray-300"></span>Sin disponibilidad</li>
                <li><span aria-hidden="true" class="mr-1 inline-block h-2 w-2 rounded-full bg-indigo-600"></span>Seleccionada</li>
                <li><span aria-hidden="true" class="mr-1 inline-block h-2 w-2 rounded-full border border-dashed border-gray-400"></span>No consultada</li>
            </ul>
            <p id="calendario-ayuda" class="mt-3 text-xs leading-5 text-gray-500">Sólo podés elegir fechas disponibles del período consultado. Navegá por semanas para consultar otras fechas.</p>
        </div>

        <fieldset class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5" aria-describedby="horarios-ayuda">
            <legend class="sr-only">Horario</legend>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-gray-900">Horarios disponibles</h3>
                <p x-show="fechaSeleccionada" x-cloak class="text-sm font-semibold text-indigo-700" x-text="fechaSeleccionada ? fechaLegible(fechaSeleccionada) : ''"></p>
            </div>
            <p id="horarios-ayuda" class="mt-2 text-sm leading-6 text-gray-600">Estos horarios corresponden a inicios posibles de Primera consulta. Seleccioná un horario para continuar al siguiente paso.</p>
            <div x-show="estadoDisponibilidad === 'idle' || estadoDisponibilidad === 'loading'" role="status" aria-live="polite" class="mt-6 rounded-xl bg-indigo-50 px-5 py-10 text-center text-sm text-indigo-900">
                <span x-show="estadoDisponibilidad === 'idle'">Preparando la consulta de disponibilidad…</span>
                <span x-show="estadoDisponibilidad === 'loading'">Consultando horarios disponibles…</span>
            </div>
            <div x-show="estadoDisponibilidad === 'empty'" x-cloak role="status" class="mt-6 space-y-3 rounded-xl bg-gray-50 px-5 py-10 text-center text-sm text-gray-700">
                <p class="text-lg font-semibold text-gray-900">No hay horarios disponibles en este período</p>
                <p>No hay horarios disponibles entre el <span x-text="fechaLegible(ventana.desde)"></span> y el <span x-text="fechaLegible(ventana.hasta)"></span>.</p>
                <p>Podés consultar otro período con Semana anterior o Semana siguiente.</p>
            </div>
            <div x-show="estadoDisponibilidad === 'error' && !recuperacion" x-cloak x-ref="errorDisponibilidad" tabindex="-1" role="alert" class="mt-6 space-y-3 rounded-xl bg-gray-50 px-5 py-10 text-center text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-lg font-semibold text-gray-900">No pudimos consultar la disponibilidad</p>
                <p x-text="errorDisponibilidad"></p>
                <button type="button" @click="consultarDisponibilidad()" class="min-h-11 rounded-lg bg-indigo-600 px-5 py-2 font-semibold text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Reintentar</button>
            </div>
            <p x-show="estadoDisponibilidad === 'success' && !fechaSeleccionada" class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">Elegí una fecha disponible para ver sus horarios.</p>
            <div x-show="fechaSeleccionada && estadoDisponibilidad === 'success'" x-cloak class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <template x-for="horario in horariosDisponibles" :key="horario.inicio_at">
                    <button type="button" @click="seleccionarInicio(horario.inicio_at)" :disabled="estadoDisponibilidad !== 'success'" :aria-label="fechaSeleccionada ? fechaLegible(fechaSeleccionada) + ', ' + horaLegible(horario.inicio_at) : ''" :aria-pressed="inicioSeleccionado === horario.inicio_at" class="min-h-11 rounded-lg border px-3 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" :class="inicioSeleccionado === horario.inicio_at ? 'border-indigo-600 bg-indigo-600 text-white ring-2 ring-indigo-200' : 'border-gray-200 bg-white text-gray-900 shadow-sm hover:border-indigo-300'">
                        <span x-show="inicioSeleccionado === horario.inicio_at" aria-hidden="true">✓ </span><span x-text="horaLegible(horario.inicio_at)"></span>
                    </button>
                </template>
            </div>
            <p class="mt-7 text-xs leading-5 text-gray-500">Seleccionar un horario no lo reserva. La disponibilidad se volverá a validar al confirmar el turno.</p>
        </fieldset>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-indigo-50 px-4 py-4 text-sm">
        <p class="font-semibold text-gray-900">Selección actual:</p>
        <div class="flex flex-wrap gap-3" aria-live="polite">
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Fecha: <strong class="text-gray-900" x-text="fechaSeleccionada ? fechaLegible(fechaSeleccionada) : 'Sin seleccionar'"></strong></p>
            <p class="rounded-lg bg-white px-3 py-2 text-gray-600">Horario: <strong class="text-indigo-700" x-text="inicioSeleccionado ? horaLegible(inicioSeleccionado) : 'Sin seleccionar'"></strong></p>
        </div>
    </div>
    <button type="button" @click="consultarDisponibilidad()" :disabled="estadoDisponibilidad === 'loading'" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">Actualizar horarios</button>
</section>
