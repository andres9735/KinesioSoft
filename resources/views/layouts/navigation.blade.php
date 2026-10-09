@php
    $user = auth()->user();

    $esAdministrativo =
        $user->esAdministrador() ||
        $user->esSuperadmin();
@endphp

<nav
    x-data="{ open: false }"
    class="bg-white border-b border-gray-100"
    aria-label="Navegación principal"
    @keydown.escape.window="open = false"
>
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" aria-label="Ir al dashboard">
                        <x-application-logo
                            class="block h-9 w-auto fill-current text-gray-800"
                            aria-hidden="true"
                            focusable="false"
                        />
                    </a>
                </div>

                <!-- Desktop Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link
                        :href="route('dashboard')"
                        :active="request()->routeIs('dashboard')"
                    >
                        Dashboard
                    </x-nav-link>

                    @if ($user->esPaciente())
                        <x-nav-link
                            :href="route('perfil.completar')"
                            :active="request()->routeIs(
                                'perfil.completar',
                                'perfil.actualizar'
                            )"
                        >
                            Mi perfil
                        </x-nav-link>
                    @endif

                    @if ($user->esKinesiologo())
                        <x-nav-link
                            :href="route('perfil.kinesiologa.completar')"
                            :active="request()->routeIs(
                                'perfil.kinesiologa.completar',
                                'perfil.kinesiologa.actualizar'
                            )"
                        >
                            Mi perfil profesional
                        </x-nav-link>

                        <x-nav-link
                            :href="route('perfil.kinesiologa.matriculas.index')"
                            :active="request()->routeIs(
                                'perfil.kinesiologa.matriculas.*'
                            )"
                        >
                            Mis matrículas
                        </x-nav-link>

                        <x-nav-link
                            :href="route('turnos.agenda.index')"
                            :active="request()->routeIs('turnos.agenda.*')"
                        >
                            Horarios de atención
                        </x-nav-link>
                    @endif

                    @if ($esAdministrativo)
                        <x-nav-link
                            :href="route('usuarios.index')"
                            :active="request()->routeIs('usuarios.*')"
                        >
                            Usuarios
                        </x-nav-link>

                        <x-nav-link
                            :href="route('catalogos.index')"
                            :active="request()->routeIs('catalogos.*')"
                        >
                            Catálogos
                        </x-nav-link>

                        <x-nav-link
                            :href="route('auditorias.index')"
                            :active="request()->routeIs('auditorias.*')"
                        >
                            Auditoría
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48" id="user-account-menu">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            aria-controls="user-account-menu"
                            :aria-expanded="open.toString()"
                        >
                            <div>
                                {{ $user->name }}
                            </div>

                            <div class="ms-1">
                                <svg
                                    class="fill-current h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link
                            :href="route('profile.edit')"
                            :active="request()->routeIs('profile.*')"
                        >
                            Mi cuenta
                        </x-dropdown-link>

                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                            class="js-confirm-action"
                            data-confirm-title="¿Cerrar sesión?"
                            data-confirm-text="Se cerrará tu sesión actual."
                            data-confirm-button="Sí, cerrar sesión"
                            data-cancel-button="Cancelar"
                            data-confirm-icon="question"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                            >
                                Cerrar sesión
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button
                    type="button"
                    @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-150 ease-in-out"
                    aria-label="Abrir o cerrar menú de navegación"
                    aria-controls="responsive-navigation-menu"
                    :aria-expanded="open.toString()"
                >
                    <svg
                        class="h-6 w-6"
                        stroke="currentColor"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            :class="{'hidden': open, 'inline-flex': ! open }"
                            class="inline-flex"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />

                        <path
                            :class="{'hidden': ! open, 'inline-flex': open }"
                            class="hidden"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div
        id="responsive-navigation-menu"
        :class="{'block': open, 'hidden': ! open}"
        class="hidden sm:hidden"
    >
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link
                :href="route('dashboard')"
                :active="request()->routeIs('dashboard')"
            >
                Dashboard
            </x-responsive-nav-link>

            @if ($user->esPaciente())
                <x-responsive-nav-link
                    :href="route('perfil.completar')"
                    :active="request()->routeIs(
                        'perfil.completar',
                        'perfil.actualizar'
                    )"
                >
                    Mi perfil
                </x-responsive-nav-link>
            @endif

            @if ($user->esKinesiologo())
                <x-responsive-nav-link
                    :href="route('perfil.kinesiologa.completar')"
                    :active="request()->routeIs(
                        'perfil.kinesiologa.completar',
                        'perfil.kinesiologa.actualizar'
                    )"
                >
                    Mi perfil profesional
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('perfil.kinesiologa.matriculas.index')"
                    :active="request()->routeIs(
                        'perfil.kinesiologa.matriculas.*'
                    )"
                >
                    Mis matrículas
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('turnos.agenda.index')"
                    :active="request()->routeIs('turnos.agenda.*')"
                >
                    Horarios de atención
                </x-responsive-nav-link>
            @endif

            @if ($esAdministrativo)
                <x-responsive-nav-link
                    :href="route('usuarios.index')"
                    :active="request()->routeIs('usuarios.*')"
                >
                    Usuarios
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('catalogos.index')"
                    :active="request()->routeIs('catalogos.*')"
                >
                    Catálogos
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('auditorias.index')"
                    :active="request()->routeIs('auditorias.*')"
                >
                    Auditoría
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">
                    {{ $user->name }} {{ $user->apellido }}
                </div>

                <div class="font-medium text-sm text-gray-500">
                    {{ $user->email }}
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link
                    :href="route('profile.edit')"
                    :active="request()->routeIs('profile.*')"
                >
                    Mi cuenta
                </x-responsive-nav-link>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    class="js-confirm-action"
                    data-confirm-title="¿Cerrar sesión?"
                    data-confirm-text="Se cerrará tu sesión actual."
                    data-confirm-button="Sí, cerrar sesión"
                    data-cancel-button="Cancelar"
                    data-confirm-icon="question"
                >
                    @csrf

                    <button
                        type="submit"
                        class="block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800 focus:outline-none"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
