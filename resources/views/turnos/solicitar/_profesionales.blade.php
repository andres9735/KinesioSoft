<section x-show="paso === 2" x-cloak aria-labelledby="titulo-paso-2" class="space-y-5">
    <h2 id="titulo-paso-2" x-ref="tituloPaso2" tabindex="-1" class="rounded-lg text-xl font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">Elegir profesional</h2>
    @if ($configuracionWizard['profesionales'] === [])
        <div class="rounded-xl bg-gray-50 p-6 text-center">
            <p class="font-semibold text-gray-900">No hay profesionales disponibles para nuevas solicitudes en este momento.</p>
            <p class="mt-2 text-sm text-gray-600">Podés volver a intentarlo más adelante o volver a Mis turnos.</p>
        </div>
    @else
        <div class="space-y-2 md:hidden">
            <x-input-label for="profesional-movil" value="Profesional para tu Primera consulta" />
            <select id="profesional-movil" x-ref="profesionalMovil" :value="kinesiologaId ?? ''" @change="seleccionarProfesional($event.target.value)" :disabled="bloqueado" aria-describedby="profesional-error" :aria-invalid="errorProfesional !== '' || !!erroresServidor.kinesiologa_id" class="block min-h-11 w-full min-w-0 rounded-lg border-gray-300 text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="" disabled>Seleccioná una profesional</option>
                @foreach ($configuracionWizard['profesionales'] as $profesional)
                    <option value="{{ $profesional['id'] }}" :disabled="profesionalesNoElegibles.includes({{ $profesional['id'] }})">{{ $profesional['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <fieldset x-ref="profesionales" tabindex="-1" aria-describedby="profesional-error" class="hidden space-y-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 md:block">
            <legend class="mb-4 text-sm text-gray-600">Seleccioná una profesional para tu Primera consulta.</legend>
            @foreach ($configuracionWizard['profesionales'] as $profesional)
                <label class="flex min-h-20 cursor-pointer items-center gap-4 rounded-xl border p-5 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2" :class="kinesiologaId === {{ $profesional['id'] }} ? 'border-indigo-600 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'">
                    <input type="radio" name="kinesiologa_id" value="{{ $profesional['id'] }}" :disabled="bloqueado || profesionalesNoElegibles.includes({{ $profesional['id'] }})" :checked="kinesiologaId === {{ $profesional['id'] }}" @change="seleccionarProfesional({{ $profesional['id'] }})" aria-describedby="profesional-error" :aria-invalid="errorProfesional !== '' || !!erroresServidor.kinesiologa_id" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="min-w-0 flex-1 break-words font-semibold text-gray-900">{{ $profesional['nombre'] }}</span>
                    <span x-show="kinesiologaId === {{ $profesional['id'] }}" x-cloak class="text-xs font-semibold text-indigo-700">Seleccionada</span>
                </label>
            @endforeach
        </fieldset>
    @endif
    <div id="profesional-error" x-ref="errorProfesional" tabindex="-1" x-show="errorProfesional || erroresServidor.kinesiologa_id" x-cloak role="alert" class="rounded-lg text-sm text-red-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <p x-show="errorProfesional" x-text="errorProfesional"></p>
        <p x-show="erroresServidor.kinesiologa_id" x-text="erroresServidor.kinesiologa_id"></p>
    </div>
</section>
