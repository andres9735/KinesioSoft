<x-guest-layout>
    <div class="space-y-6">
        <div class="text-center">
            <h1 class="text-xl font-semibold text-gray-900">
                Verifique su correo electrónico
            </h1>

            <p class="mt-2 text-sm leading-6 text-gray-600">
                Para continuar utilizando KinesioSoft, debe verificar
                la dirección de correo electrónico asociada a su cuenta.
            </p>
        </div>

        <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
            <p class="text-sm text-indigo-900">
                Enviamos un enlace de verificación a:
            </p>

            <p class="mt-1 break-all text-sm font-semibold text-indigo-900">
                {{ auth()->user()->email }}
            </p>
        </div>

        @if (session('status') === 'verification-link-sent')
            <div
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3"
                role="status"
            >
                <p class="text-sm font-medium text-green-800">
                    Se envió un nuevo enlace de verificación a su correo electrónico.
                </p>
            </div>
        @endif

        <p class="text-sm leading-6 text-gray-600">
            Revise su bandeja de entrada y siga el enlace recibido.
            Si no encuentra el mensaje, también puede revisar la carpeta
            de correo no deseado o solicitar un nuevo envío.
        </p>

        <form
            method="POST"
            action="{{ route('verification.send') }}"
        >
            @csrf

            <x-primary-button class="w-full justify-center">
                Reenviar correo de verificación
            </x-primary-button>
        </form>

        <div class="flex items-center justify-between border-t border-gray-200 pt-4">
            <a
                href="{{ route('profile.edit') }}"
                class="text-sm font-medium text-indigo-600 underline decoration-indigo-300 underline-offset-4 transition hover:text-indigo-800"
            >
                Mi cuenta
            </a>

            <form
                method="POST"
                action="{{ route('logout') }}"
            >
                @csrf

                <button
                    type="submit"
                    class="text-sm font-medium text-gray-600 underline decoration-gray-300 underline-offset-4 transition hover:text-gray-900"
                >
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
