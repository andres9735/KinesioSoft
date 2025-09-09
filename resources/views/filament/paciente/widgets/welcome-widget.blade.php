<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-2">
            <h2 class="text-xl font-bold">¡Hola, {{ auth()->user()->name }}!</h2>
            <p>Este es tu escritorio de paciente. Aquí vas a ver tus próximos turnos, indicaciones y mensajes.</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

