<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>

            <p class="text-sm text-gray-500">
                Acceda a las funciones disponibles según su rol en KinesioSoft.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-indigo-600">
                                KinesioSoft
                            </p>

                            <h3 class="text-2xl font-semibold text-gray-900">
                                Hola, {{ $user->name }}.
                            </h3>

                            <p class="text-sm text-gray-500">
                                Desde este panel puede acceder a las funciones habilitadas para su cuenta.
                            </p>
                        </div>

                        <div class="shrink-0">
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200">
                                {{ $rolLabel }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            @include($dashboardPartial)
        </div>
    </div>
</x-app-layout>
