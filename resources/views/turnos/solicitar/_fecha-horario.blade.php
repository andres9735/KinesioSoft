<section x-show="paso === 3" x-cloak aria-labelledby="titulo-paso-3" class="space-y-5">
    <h2 id="titulo-paso-3" x-ref="tituloPaso3" tabindex="-1" class="text-xl font-semibold text-gray-900 focus:outline-none">Fecha y horario</h2>
    <p class="text-sm text-gray-700">Profesional: <span class="font-semibold" x-text="profesionalSeleccionada?.nombre || 'Sin seleccionar'"></span></p>
    <div class="rounded-xl bg-gray-50 p-6 text-sm leading-6 text-gray-600">
        <p class="font-semibold text-gray-900">Todavía no seleccionaste fecha ni horario.</p>
        <p class="mt-2">La selección de horarios aún no está habilitada. Podés volver al paso anterior para revisar la profesional.</p>
    </div>
</section>
