<nav aria-label="Catálogos maestros" class="space-y-6">
    <section aria-labelledby="catalogos-profesionales" class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
        <div class="border-b bg-gray-50 px-6 py-4">
            <h3 id="catalogos-profesionales" class="text-lg font-semibold text-gray-800">Profesionales</h3>
            <p class="mt-1 text-sm text-gray-500">Seleccione un catálogo para administrarlo.</p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
            <a href="{{ route('catalogos.jurisdicciones.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Jurisdicciones</h4>
                <p class="mt-2 text-sm text-gray-500">Provincias o jurisdicciones emisoras de matrícula.</p>
            </a>

            <a href="{{ route('catalogos.colegios.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Colegios emisores</h4>
                <p class="mt-2 text-sm text-gray-500">Entidades responsables de emitir matrículas profesionales.</p>
            </a>

            <a href="{{ route('catalogos.titulos.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Títulos profesionales</h4>
                <p class="mt-2 text-sm text-gray-500">Títulos habilitantes para el ejercicio profesional.</p>
            </a>

            <a href="{{ route('catalogos.especialidades.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Especialidades de kinesiología</h4>
                <p class="mt-2 text-sm text-gray-500">Áreas profesionales para asociar al perfil de cada kinesióloga.</p>
            </a>
        </div>
    </section>

    <section aria-labelledby="catalogos-clinicos" class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 overflow-hidden">
        <div class="border-b bg-gray-50 px-6 py-4">
            <h3 id="catalogos-clinicos" class="text-lg font-semibold text-gray-800">Clínicos</h3>
            <p class="mt-1 text-sm text-gray-500">Administre diagnósticos, pruebas funcionales, técnicas terapéuticas, categorías clínicas, ejercicios domiciliarios y tipos de equipo terapéutico.</p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <a href="{{ route('catalogos.diagnosticos.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Diagnósticos clínicos</h4>
                <p class="mt-2 text-sm text-gray-500">Conceptos diagnósticos codificados para uso clínico.</p>
            </a>

            <a href="{{ route('catalogos.pruebas.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Pruebas funcionales</h4>
                <p class="mt-2 text-sm text-gray-500">Pruebas, escalas y mediciones disponibles para evaluación funcional.</p>
            </a>

            <a href="{{ route('catalogos.tecnicas.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Técnicas terapéuticas</h4>
                <p class="mt-2 text-sm text-gray-500">Técnicas disponibles para prescripción y seguimiento clínico.</p>
            </a>

            <a href="{{ route('catalogos.categorias.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Categorías clínicas</h4>
                <p class="mt-2 text-sm text-gray-500">Clasificaciones anatómicas, funcionales, terapéuticas y de equipamiento.</p>
            </a>

            <a href="{{ route('catalogos.ejercicios.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-800">Ejercicios domiciliarios</h4>
                <p class="mt-2 text-sm text-gray-500">Ejercicios con parámetros base, clasificación clínica y recursos multimedia.</p>
            </a>

            <a href="{{ route('catalogos.tipos-equipo.index') }}"
               class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                <h4 class="text-base font-semibold text-gray-700">Tipos de equipo terapéutico</h4>
                <p class="mt-2 text-sm text-gray-500">Clases clínicas de aparatología asociadas a categorías de equipamiento.</p>
            </a>
        </div>
    </section>
</nav>
