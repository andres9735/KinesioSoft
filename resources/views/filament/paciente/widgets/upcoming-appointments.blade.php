<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Próximos turnos</h3>

            {{-- Si más adelante agregás “crear turno”, podés poner un botón acá --}}
            {{-- <x-filament::button tag="a" href="{{ route('lo-que-sea') }}">Nuevo turno</x-filament::button> --}}
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left">
                    <tr class="border-b border-gray-200/10">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Hora</th>
                        <th class="py-2 pr-4">Profesional</th>
                        <th class="py-2 pr-4">Lugar</th>
                        <th class="py-2 pr-4">Estado</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($appointments as $a)
                        <tr class="border-b border-gray-200/10 last:border-0">
                            <td class="py-2 pr-4">{{ $a['fecha'] }}</td>
                            <td class="py-2 pr-4">{{ $a['hora'] }}</td>
                            <td class="py-2 pr-4">{{ $a['profesional'] }}</td>
                            <td class="py-2 pr-4">{{ $a['lugar'] }}</td>
                            <td class="py-2 pr-4">
                                @php
                                    $color = match ($a['estado']) {
                                        'Confirmado' => 'success',
                                        'Cancelado'  => 'danger',
                                        default      => 'warning',
                                    };
                                @endphp

                                <x-filament::badge color="{{ $color }}">
                                    {{ $a['estado'] }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-gray-400">
                                No tenés turnos próximos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
