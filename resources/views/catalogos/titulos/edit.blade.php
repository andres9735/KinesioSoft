<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    Editar título profesional
                </h2>
                <p class="text-sm text-gray-500">
                    Modifique la información del título seleccionado.
                </p>
            </div>

            <a href="{{ route('catalogos.titulos.index') }}"
               class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-100">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded-2xl ring-1 ring-gray-100 overflow-hidden">

                <form method="POST" action="{{ route('catalogos.titulos.update', $titulo) }}" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Nombre -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Nombre del título profesional
                        </label>

                        <input type="text"
                               name="nombre"
                               value="{{ old('nombre', $titulo->nombre) }}"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">

                        @error('nombre')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Botones -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t">

                        <a href="{{ route('catalogos.titulos.index') }}"
                           class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                            Cancelar
                        </a>

                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700">
                            Actualizar
                        </button>

                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- SweetAlert errores -->
    @if ($errors->any())
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Revise los datos ingresados.',
                confirmButtonColor: '#6366f1'
            });
        </script>
    @endif

</x-app-layout>
