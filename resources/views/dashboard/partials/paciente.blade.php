<section class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">
            Accesos personales
        </h3>

        <p class="mt-1 text-sm text-gray-500">
            Consulte y mantenga actualizada la información disponible de su cuenta.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a
            href="{{ route('perfil.completar') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Mi perfil
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Consulte y mantenga actualizados sus datos personales.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Ver mi perfil →
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
