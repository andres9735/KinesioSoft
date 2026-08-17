<section>
    <header>
        <h3 class="text-lg font-semibold text-gray-900">
            Información de la cuenta
        </h3>

        <p class="mt-1 text-sm text-gray-600">
            Actualice su nombre, apellido y correo electrónico.
        </p>
    </header>

    <form
        id="send-verification"
        method="POST"
        action="{{ route('verification.send') }}"
    >
        @csrf
    </form>

    <form
        method="POST"
        action="{{ route('profile.update') }}"
        class="mt-6 space-y-6"
    >
        @csrf
        @method('PATCH')

        <div>
            <x-input-label
                for="name"
                value="Nombre"
            />

            <x-text-input
                id="name"
                name="name"
                type="text"
                class="mt-1 block w-full"
                :value="old('name', $user->name)"
                required
                autofocus
                autocomplete="given-name"
            />

            <x-input-error
                class="mt-2"
                :messages="$errors->get('name')"
            />
        </div>

        <div>
            <x-input-label
                for="apellido"
                value="Apellido"
            />

            <x-text-input
                id="apellido"
                name="apellido"
                type="text"
                class="mt-1 block w-full"
                :value="old('apellido', $user->apellido)"
                required
                autocomplete="family-name"
            />

            <x-input-error
                class="mt-2"
                :messages="$errors->get('apellido')"
            />
        </div>

        <div>
            <x-input-label
                for="email"
                value="Correo electrónico"
            />

            <x-text-input
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full"
                :value="old('email', $user->email)"
                required
                autocomplete="email"
            />

            <x-input-error
                class="mt-2"
                :messages="$errors->get('email')"
            />

            @if (
                $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
                && ! $user->hasVerifiedEmail()
            )
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm text-amber-900">
                        Su dirección de correo electrónico todavía no fue verificada.

                        <button
                            form="send-verification"
                            class="font-semibold underline decoration-amber-400 underline-offset-4 transition hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                        >
                            Reenviar correo de verificación
                        </button>
                    </p>

                    @if (
                        session('status')
                        === 'verification-link-sent'
                    )
                        <p class="mt-2 text-sm font-medium text-green-700">
                            Se envió un nuevo enlace de verificación a su correo electrónico.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>
                Guardar cambios
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-medium text-green-700"
                >
                    Cambios guardados.
                </p>
            @endif
        </div>
    </form>
</section>
