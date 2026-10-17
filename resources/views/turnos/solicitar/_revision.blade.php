<section x-show="paso === 4" x-cloak aria-labelledby="titulo-paso-4" class="space-y-5">
    <h2 id="titulo-paso-4" x-ref="tituloPaso4" tabindex="-1" class="rounded-lg text-xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Revisar y confirmar</h2>
    <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-2">
        <div><dt class="text-gray-500">Tipo</dt><dd class="font-semibold text-gray-900">Primera consulta</dd></div>
        <div><dt class="text-gray-500">Profesional</dt><dd class="font-semibold text-gray-900" x-text="profesionalSeleccionada?.nombre || 'Sin seleccionar'"></dd></div>
        <div><dt class="text-gray-500">Fecha</dt><dd class="text-gray-900" x-text="slotSeleccionado ? fechaLegible(slotSeleccionado.inicio_at.slice(0, 10)) : 'Pendiente de selección'">Pendiente de selección</dd></div>
        <div><dt class="text-gray-500">Horario</dt><dd class="text-gray-900" x-text="slotSeleccionado ? horaLegible(slotSeleccionado.inicio_at) + ' – ' + horaLegible(slotSeleccionado.fin_at) : 'Pendiente de selección'">Pendiente de selección</dd></div>
        <div class="sm:col-span-2"><dt class="text-gray-500">Motivo</dt><dd class="whitespace-pre-wrap break-words text-gray-900" x-text="motivo"></dd></div>
        <div class="sm:col-span-2">
            <dt class="text-gray-500">Documentación seleccionada</dt>
            <dd>
                <p x-show="archivosValidos.length === 0" class="text-gray-600">Sin archivos</p>
                <ul class="space-y-1"><template x-for="entrada in archivos" :key="entrada.id"><li class="break-words text-gray-900" x-text="entrada.archivo.name + ' · ' + tamanioLegible(entrada.archivo.size)"></li></template></ul>
            </dd>
        </div>
        <div><dt class="text-gray-500">Estado al registrar</dt><dd class="text-gray-900">Pendiente</dd></div>
    </dl>
    <p class="text-sm text-gray-600">Al confirmar, el turno queda registrado en estado Pendiente y ocupa agenda.</p>
    <button type="button" @click="confirmarTurno()" :disabled="!puedeConfirmar" :aria-busy="enviando" x-text="enviando ? 'Registrando turno…' : 'Confirmar turno'" class="min-h-11 w-full rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 sm:w-auto">Confirmar turno</button>
</section>
