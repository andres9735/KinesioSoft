{{-- resources/views/agenda_diaria/preview.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-5xl">

    <h1 class="text-2xl font-semibold mb-4">Agenda automática – Previsualización (D+1)</h1>

    {{-- Flash del resultado al ejecutar el módulo --}}
    @if (session('agenda_result'))
        @php $s = session('agenda_result'); @endphp
        <div class="p-4 mb-4 rounded border {{ $s['errores'] ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50' }}">
            <div class="font-semibold">
                Ejecutado para {{ \Illuminate\Support\Carbon::parse($s['fecha_objetivo'])->format('d/m/Y') }} —
                Enviados: {{ $s['enviados'] }} / {{ $s['total_turnos'] }} —
                Errores: {{ $s['errores'] }}
            </div>
            <div class="text-sm mt-1">
                <strong>Modo {{ $s['modo'] ?? ($s['simulate'] ? 'SIMULADO' : 'REAL') }}:</strong>
                {{ $s['msg'] ?? '' }}
            </div>
        </div>
    @endif

    <p class="mb-2">Hoy: <strong>{{ $hoy }}</strong></p>
    <p class="mb-4">Fecha objetivo (D+1): <strong>{{ $fecha_objetivo }}</strong></p>

    @if ($total === 0)
        <div class="p-4 rounded bg-yellow-100 border border-yellow-300 mb-6">
            No hay turnos para notificar mañana.
        </div>
    @else
        <div class="p-4 rounded bg-blue-50 border border-blue-200 mb-6">
            Se enviará un correo de confirmación a los siguientes pacientes para sus turnos en el día
            <strong>{{ \Illuminate\Support\Carbon::parse($fecha_objetivo)->format('d/m/Y') }}</strong>.
        </div>

        <table class="min-w-full text-sm border mb-6">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Paciente</th>
                    <th class="p-2 text-left">Email</th>
                    <th class="p-2 text-left">Profesional</th>
                    <th class="p-2 text-left">Horario</th>
                    <th class="p-2 text-left">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detalle as $row)
                    <tr class="border-t">
                        <td class="p-2">{{ $row['paciente'] }}</td>
                        <td class="p-2">{{ $row['email'] }}</td>
                        <td class="p-2">{{ $row['profesional'] }}</td>
                        <td class="p-2">{{ $row['hora_desde'] }}–{{ $row['hora_hasta'] }}</td>
                        <td class="p-2">{{ $row['estado'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Botón que dispara el módulo inteligente --}}
        <form method="POST" action="{{ route('agenda-diaria.enviar') }}" class="flex items-center gap-3">
            @csrf

            {{-- Hidden que almacena el verdadero valor enviado --}}
            <input type="hidden" name="simulate" id="simulate-hidden" value="1">

            <label class="inline-flex items-center gap-2">
                {{-- el checkbox NO tiene name; solo controla el hidden --}}
                <input type="checkbox" id="simulate-check" checked>
                <span>Ejecutar en modo simulado (no envía correos, solo loguea)</span>
            </label>

            <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">
                Ejecutar módulo ahora
            </button>
        </form>

        <script>
        document.getElementById('simulate-check').addEventListener('change', function () {
            document.getElementById('simulate-hidden').value = this.checked ? '1' : '0';
        });
        </script>
    @endif
</div>
@endsection

