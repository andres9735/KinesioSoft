@props(['titulo', 'descripcion'])

<div class="mx-auto max-w-lg rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center">
    <h4 class="text-base font-semibold text-gray-900">{{ $titulo }}</h4>
    <p class="mt-2 text-sm text-gray-500">{{ $descripcion }}</p>
    {{ $slot }}
</div>
