{{-- resources/views/recordatorio/form.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">Confirmación de turno</h1>

    {{-- Mensajes flash --}}
    @foreach (['success' => 'bg-green-50 border-green-300 text-green-800',
               'error'   => 'bg-red-50 border-red-300 text-red-800',
               'info'    => 'bg-blue-50 border-blue-300 text-blue-800'] as $key => $cls)
        @if (session($key))
            <div class="mb-4 p-3 border rounded {{ $cls }}">
                {{ session($key) }}
            </div>
        @endif
    @endforeach

    <div class="rounded border p-4 mb-6">
        <p class="mb-1"><strong>Paciente:</strong> {{ $turno->paciente?->name }}</p>
        <p class="mb-1"><strong>Profesional:</strong> {{ $turno->profesional?->name }}</p>
        <p class="mb-1"><strong>Fecha:</strong> {{ $turno->fecha?->isoFormat('DD/MM/YYYY') }}</p>
        <p class="mb-1"><strong>Horario:</strong> {{ \Illuminate\Support\Str::of((string)$turno->hora_desde)->substr(0,5) }}–{{ \Illuminate\Support\Str::of((string)$turno->hora_hasta)->substr(0,5) }}</p>
        <p class="mb-0"><strong>Estado actual:</strong> {{ $turno->estado }}</p>
    </div>

    @if ($turno->esPendiente())
        <div class="mb-6 space-y-3">
            {{-- Confirmar --}}
            <form method="POST" action="{{ route('recordatorio.confirmar', $turno->reminder_token) }}">
                @csrf
                <button type="submit"
                        {{ $puedeConfirmar ? '' : 'disabled' }}
                        class="px-4 py-2 rounded text-white {{ $puedeConfirmar ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-400 cursor-not-allowed' }}">
                    Confirmar asistencia
                </button>
                @unless($puedeConfirmar)
                    <p class="text-sm text-gray-600 mt-1">
                        No es posible confirmar por política de tiempo.
                        @if ($limiteConfirm)
                            Límite: {{ $limiteConfirm->isoFormat('DD/MM HH:mm') }}.
                        @endif
                    </p>
                @endunless
            </form>

            {{-- Cancelar --}}
            <form method="POST" action="{{ route('recordatorio.cancelar', $turno->reminder_token) }}">
                @csrf
                <button type="submit"
                        {{ $puedeCancelar ? '' : 'disabled' }}
                        class="px-4 py-2 rounded text-white {{ $puedeCancelar ? 'bg-rose-600 hover:bg-rose-700' : 'bg-gray-400 cursor-not-allowed' }}">
                    Cancelar turno
                </button>
                @unless($puedeCancelar)
                    <p class="text-sm text-gray-600 mt-1">
                        No es posible cancelar un turno pasado o en curso.
                    </p>
                @endunless
            </form>
        </div>
    @else
        <div class="p-3 border rounded bg-gray-50">
            Este turno ya no está pendiente (estado: <strong>{{ $turno->estado }}</strong>).
        </div>
    @endif
</div>
@endsection
