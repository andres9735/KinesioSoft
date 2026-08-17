<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                Mi cuenta
            </h2>

            <p class="text-sm text-gray-500">
                Administre sus datos de cuenta y credenciales de acceso.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="p-6 sm:p-8">
                    <div class="max-w-2xl">
                        @include(
                            'profile.partials.update-profile-information-form'
                        )
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="p-6 sm:p-8">
                    <div class="max-w-2xl">
                        @include(
                            'profile.partials.update-password-form'
                        )
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
