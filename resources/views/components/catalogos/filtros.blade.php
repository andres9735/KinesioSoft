@props(['action', 'q', 'estado', 'placeholder'])

<form method="GET" action="{{ $action }}" class="grid gap-4 border-b border-gray-100 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end sm:p-6">
    <div>
        <label for="q" class="block text-sm font-medium text-gray-700">Buscar por nombre</label>
        <input id="q" name="q" type="search" value="{{ $q }}" placeholder="{{ $placeholder }}"
               class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
    </div>
    <div>
        <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
        <select id="estado" name="estado" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
            <option value="todos" @selected($estado === 'todos')>Todos</option>
            <option value="activos" @selected($estado === 'activos')>Activos</option>
            <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
        </select>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 sm:flex-none">Buscar</button>
        <a href="{{ $action }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:flex-none">Limpiar filtros</a>
    </div>
</form>
