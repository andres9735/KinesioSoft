<section class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">
            Herramientas profesionales
        </h3>

        <p class="mt-1 text-sm text-gray-500">
            Acceda a la información actualmente disponible para su actividad profesional.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <a
            href="{{ route('perfil.kinesiologa.completar') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Mi perfil profesional
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Consulte y mantenga actualizados sus datos personales y profesionales.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Ver perfil profesional →
            </div>
        </a>

        <a
            href="{{ route('perfil.kinesiologa.matriculas.index') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Mis matrículas
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Administre sus matrículas profesionales por jurisdicción y colegio emisor.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Administrar matrículas →
            </div>
        </a>

        <a
            href="{{ route('profile.edit') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Mi cuenta
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Actualice sus datos de cuenta y credenciales de acceso.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Administrar mi cuenta →
            </div>
        </a>
    </div>
</section>
