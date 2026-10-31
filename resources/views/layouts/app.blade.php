@php($esMisTurnos = request()->routeIs('turnos.mis-turnos.*'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        @unless ($esMisTurnos)
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @endunless

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class(['font-sans antialiased', 'uc06-mis-turnos' => $esMisTurnos])>
        @if ($esMisTurnos)
            <a href="#mis-turnos-contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-indigo-800 focus:ring-2 focus:ring-indigo-500">Saltar al contenido de Mis turnos</a>
        @endif
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main @if ($esMisTurnos) id="mis-turnos-contenido" tabindex="-1" class="focus:outline-none" @endif>
                {{ $slot }}
            </main>
        </div>

        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: @json(session('success')),
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            </script>
        @endif

        @if (session('error'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: @json(session('error')),
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            </script>
        @endif

        @if (session('warning'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: @json(session('warning')),
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            </script>
        @endif

        @if (session('info'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Información',
                            text: @json(session('info')),
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            </script>
        @endif

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const forms = document.querySelectorAll('.js-confirm-action');

                forms.forEach(form => {
                    form.addEventListener('submit', function (event) {
                        event.preventDefault();

                        const title = form.dataset.confirmTitle || '¿Confirmar acción?';
                        const text = form.dataset.confirmText || 'Esta acción requiere confirmación.';
                        const confirmButtonText = form.dataset.confirmButton || 'Sí, continuar';
                        const cancelButtonText =
                            form.dataset.cancelButton || 'Cancelar';
                        const icon = form.dataset.confirmIcon || 'warning';

                        if (window.Swal) {
                            Swal.fire({
                                icon: icon,
                                title: title,
                                text: text,
                                showCancelButton: true,
                                confirmButtonText: confirmButtonText,
                                cancelButtonText: cancelButtonText,
                                reverseButtons: true,
                                focusCancel: form.dataset.confirmFocusCancel === 'true',
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    form.submit();
                                }
                            });
                        } else {
                            form.submit();
                        }
                    });
                });
            });
        </script>
    </body>
</html>
