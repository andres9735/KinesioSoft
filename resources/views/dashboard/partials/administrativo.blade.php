<section class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">
            Accesos administrativos
        </h3>

        <p class="mt-1 text-sm text-gray-500">
            Gestione las funciones generales de administración del sistema.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a
            href="{{ route('usuarios.index') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Usuarios
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Administre cuentas, roles y estado de acceso de los usuarios.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Gestionar usuarios →
            </div>
        </a>

        <a
            href="{{ route('catalogos.index') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Catálogos
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Mantenga los catálogos generales utilizados por KinesioSoft.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Gestionar catálogos →
            </div>
        </a>

        <a
            href="{{ route('auditorias.index') }}"
            class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50"
        >
            <div class="space-y-2">
                <h4 class="text-base font-semibold text-gray-900 transition group-hover:text-indigo-700">
                    Auditoría
                </h4>

                <p class="text-sm leading-6 text-gray-500">
                    Consulte la trazabilidad de las operaciones registradas en el sistema.
                </p>
            </div>

            <div class="mt-4 text-sm font-semibold text-indigo-600">
                Consultar auditoría →
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
