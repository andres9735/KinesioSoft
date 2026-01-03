<x-filament-panels::page>
    {{-- Header --}}
    <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Evaluación inicial</h1>

                {{-- 🟡 B.2 — Badge de estado de la consulta --}}
                <div class="flex items-center gap-2 mt-2">
                    @if($this->soloLectura)
                        <span class="inline-flex items-center rounded-full bg-gray-200 dark:bg-white/10 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:text-gray-200">
                            Solo lectura
                        </span>
                    @elseif($this->pasoMaximo < \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_CIERRE)
                        <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-200">
                            Borrador
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-200">
                            Finalizada
                        </span>
                    @endif
                </div>

                <div class="mt-2 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <div>
                        <strong>Paciente:</strong>
                        {{ $this->turno->pacientePerfil?->nombre ?? $this->turno->paciente?->name ?? '—' }}
                    </div>
                    <div>
                        <strong>Fecha:</strong>
                        {{ \Illuminate\Support\Carbon::parse($this->turno->fecha)->format('d/m/Y') }}
                    </div>
                    <div>
                        <strong>Horario:</strong>
                        {{ substr((string) $this->turno->hora_desde, 0, 5) }}–{{ substr((string) $this->turno->hora_hasta, 0, 5) }}
                    </div>
                    <div>
                        <strong>Consultorio:</strong>
                        {{ $this->turno->consultorio?->nombre ?? '—' }}
                    </div>
                    <div>
                        <strong>Estado turno:</strong>
                        {{ $this->turno->estado }}
                    </div>
                </div>

                {{-- Aviso modo solo lectura (explicativo, queda perfecto que siga existiendo) --}}
                @if($this->soloLectura ?? false)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                        Esta consulta está en <strong>solo lectura</strong>.
                        <span class="block mt-1">
                            Motivos típicos: el turno está cancelado/no asistió, o la consulta ya fue finalizada.
                        </span>
                    </div>
                @endif
            </div>

            <a
                href="{{ \App\Filament\Kinesiologa\Pages\AgendaDeHoy::getUrl(['fecha' => $this->turno->fecha]) }}"
                class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-white/10 px-3 py-2 text-sm font-medium hover:bg-gray-200 dark:hover:bg-white/20"
            >
                ← Volver a agenda
            </a>
        </div>
    </div>

    {{-- =========================
         Tabs (MODO PRO)
         ========================= --}}
    @php
        $paso = (int) ($this->pasoActual ?? 1);
        $max  = (int) ($this->pasoMaximo ?? 1);

        $tabs = [
            \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_DATOS       => 'Datos',
            \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_ANAMNESIS   => 'Anamnesis',
            \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_EVALUACION  => 'Evaluación',
            \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_DIAGNOSTICO => 'Diagnóstico',
            \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_CIERRE      => 'Cierre',
        ];
    @endphp

    <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach($tabs as $id => $label)
                @php
                    $activo    = $paso === $id;
                    $bloqueado = $id > $max;
                @endphp

                <button
                    type="button"
                    wire:click="irAlPaso({{ $id }})"
                    @disabled($bloqueado)
                    title="{{ $bloqueado ? 'Guardá el paso actual para desbloquear este.' : '' }}"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition inline-flex items-center gap-2
                        {{ $activo
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20'
                        }}
                        @if($bloqueado) opacity-60 cursor-not-allowed hover:bg-gray-100 dark:hover:bg-white/10 @endif"
                >
                    @if($bloqueado)
                        <x-heroicon-o-lock-closed class="w-4 h-4" />
                    @endif

                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Paso actual: <strong>{{ $tabs[$paso] ?? '—' }}</strong>
            · Desbloqueado hasta: <strong>{{ $tabs[$max] ?? '—' }}</strong>

            @if(($this->soloLectura ?? false))
                · Solo lectura (podés navegar, pero no guardar)
            @endif
        </div>
    </div>

    {{-- ✅ Aviso wizard (debajo tabs) --}}
    @if($this->avisoWizard)
        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800
                    dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
            {{ $this->avisoWizard }}
        </div>
    @endif

    {{-- =========================
     PASO 1 — Datos
     ========================= --}}
    @if($paso === \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_DATOS)
        @php
            $bloqueado = (bool) ($this->soloLectura ?? false);
        @endphp

        <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6">
            <h2 class="text-lg font-semibold">Datos básicos</h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Información del turno y del paciente.
            </p>

            {{-- 🔴 B.3 — Bloqueo explicativo (solo lectura) --}}
            @if($bloqueado)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                            dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    Esta sección está en <strong>solo lectura</strong> porque la consulta fue finalizada
                    o el turno está cancelado/no asistió.
                </div>
            @endif

            <div class="mt-4 text-sm text-gray-700 dark:text-gray-200 space-y-1">
                <div>
                    <strong>Paciente:</strong>
                    {{ $this->turno->pacientePerfil?->nombre ?? $this->turno->paciente?->name ?? '—' }}
                </div>

                <div>
                    <strong>Fecha:</strong>
                    {{ \Illuminate\Support\Carbon::parse($this->turno->fecha)->format('d/m/Y') }}
                </div>

                <div>
                    <strong>Horario:</strong>
                    {{ substr((string) $this->turno->hora_desde, 0, 5) }}–{{ substr((string) $this->turno->hora_hasta, 0, 5) }}
                </div>

                <div>
                    <strong>Consultorio:</strong>
                    {{ $this->turno->consultorio?->nombre ?? '—' }}
                </div>

                <div>
                    <strong>Estado turno:</strong>
                    {{ $this->turno->estado }}
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    wire:click="guardarDatosYContinuar"
                    wire:loading.attr="disabled"
                    wire:target="guardarDatosYContinuar"
                    @disabled($bloqueado)
                    class="rounded-lg px-4 py-2 text-sm font-semibold
                        @if($bloqueado)
                            bg-gray-300 text-gray-700 cursor-not-allowed
                            dark:bg-white/10 dark:text-gray-300
                        @else
                            bg-indigo-600 text-white hover:bg-indigo-700
                        @endif"
                >
                    <span wire:loading.remove wire:target="guardarDatosYContinuar">
                        Continuar →
                    </span>

                    <span wire:loading wire:target="guardarDatosYContinuar">
                        Procesando...
                    </span>
                </button>
            </div>
        </div>
    @endif

    {{-- =========================
     PASO 2 — Anamnesis
     ========================= --}}
    @if($paso === \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_ANAMNESIS)
        @php
            $bloqueado = (bool) ($this->soloLectura ?? false);

            $tieneAlgo = trim((string) ($this->motivoConsulta ?? '')) !== ''
                || ($this->evaDolor !== null && $this->evaDolor !== '')
                || !empty($this->limitacionFuncional);
        @endphp

        <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Anamnesis</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Guardá este paso para desbloquear <strong>Evaluación</strong>.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    @if($bloqueado)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            Solo lectura
                        </span>
                    @elseif($tieneAlgo)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                            Cambios sin guardar
                        </span>
                    @endif
                </div>
            </div>

            {{-- 🔴 B.3 — Bloqueo unificado --}}
            @if($bloqueado)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                            dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    Esta sección está en <strong>solo lectura</strong> porque la consulta fue finalizada
                    o el turno está cancelado/no asistió.
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-12 gap-4">
                {{-- Motivo --}}
                <div class="md:col-span-8">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Motivo de consulta</label>

                    <textarea
                        wire:model.debounce.600ms="motivoConsulta"
                        rows="4"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                            @if($bloqueado) opacity-60 cursor-not-allowed @endif"
                        placeholder="¿Qué le pasa? ¿Desde cuándo? ¿Cómo comenzó?"
                        @disabled($bloqueado)
                    ></textarea>

                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Sugerencia: breve, clínico y orientado a función.
                    </p>
                </div>

                {{-- EVA --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Dolor (EVA)</label>

                    <input
                        type="number"
                        min="0"
                        max="10"
                        inputmode="numeric"
                        wire:model.live="evaDolor"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                            @if($bloqueado) opacity-60 cursor-not-allowed @endif"
                        placeholder="0–10"
                        @disabled($bloqueado)
                    />

                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        0 = sin dolor · 10 = máximo
                    </p>
                </div>

                {{-- Limitación funcional --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Limitación</label>

                    <select
                        wire:model.live="limitacionFuncional"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                            @if($bloqueado) opacity-60 cursor-not-allowed @endif"
                        @disabled($bloqueado)
                    >
                        <option value="">— Seleccionar —</option>
                        @foreach(($this->limitacionOptions ?? []) as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Impacto en actividades.
                    </p>
                </div>

                {{-- Resumen --}}
                <div class="md:col-span-12">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Resumen / Observaciones</label>

                    <textarea
                        wire:model.debounce.600ms="resumen"
                        rows="4"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                            @if($bloqueado) opacity-60 cursor-not-allowed @endif"
                        placeholder="Hallazgos relevantes, notas clínicas, etc."
                        @disabled($bloqueado)
                    ></textarea>
                </div>
            </div>

            {{-- Botones por paso (Anamnesis) --}}
            <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Guardá este paso para desbloquear el siguiente.
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="irAlPaso({{ \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_DATOS }})"
                        class="rounded-lg px-4 py-2 text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                    >
                        ← Volver
                    </button>

                    <button
                        type="button"
                        wire:click="guardarAnamnesis"
                        wire:loading.attr="disabled"
                        @disabled($bloqueado)
                        class="rounded-lg px-4 py-2 text-sm font-semibold
                            @if($bloqueado)
                                bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                            @else
                                bg-emerald-600 text-white hover:bg-emerald-700
                            @endif"
                    >
                        <span wire:loading.remove wire:target="guardarAnamnesis">Guardar anamnesis</span>
                        <span wire:loading wire:target="guardarAnamnesis">Guardando…</span>
                    </button>

                    <button
                        type="button"
                        wire:click="guardarAnamnesisYContinuar"
                        wire:loading.attr="disabled"
                        wire:target="guardarAnamnesisYContinuar"
                        @disabled($bloqueado)
                        class="rounded-lg px-4 py-2 text-sm font-semibold
                            @if($bloqueado)
                                bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                            @else
                                bg-indigo-600 text-white hover:bg-indigo-700
                            @endif"
                    >
                        <span wire:loading.remove wire:target="guardarAnamnesisYContinuar">Guardar y continuar →</span>
                        <span wire:loading wire:target="guardarAnamnesisYContinuar">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif


   {{-- =========================
     PASO 3 — Evaluación (ROM)
     ========================= --}}
    @if($paso === \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_EVALUACION)
        @php
            $soloLectura   = (bool) ($this->soloLectura ?? false);
            $puedeEditarRom = (bool) ($this->puedeEditarRom ?? false);
            $romBloqueado   = ! $puedeEditarRom;
        @endphp

        <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Evaluación</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Guardá este paso para desbloquear Diagnóstico/Cierre.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="addRomRow"
                    @disabled($romBloqueado || $soloLectura)
                    class="rounded-lg px-3 py-2 text-sm font-semibold
                        @if($romBloqueado || $soloLectura)
                            bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                        @else
                            bg-indigo-600 text-white hover:bg-indigo-700
                        @endif"
                >
                    + Agregar ROM
                </button>
            </div>

            {{-- 🔴 B.3 — Bloqueo unificado (solo lectura) --}}
            @if($soloLectura)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                            dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    Esta sección está en <strong>solo lectura</strong> porque la consulta fue finalizada
                    o el turno está cancelado/no asistió.
                </div>
            @endif

            {{-- 🟠 Aviso específico de ROM (solo cuando NO es solo lectura) --}}
            @if($romBloqueado && ! $soloLectura)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                            dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    Los <strong>ROM</strong> están bloqueados porque <strong>solo se pueden cargar/editar cuando el turno ya finalizó</strong>
                    y no está cancelado/no asistió.
                    Podés seguir navegando y ver la información.
                </div>
            @endif

            {{-- Resumen --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Resumen / Observaciones
                </label>

                <textarea
                    wire:model.debounce.600ms="resumen"
                    rows="6"
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                        @if($romBloqueado || $soloLectura) opacity-60 cursor-not-allowed @endif"
                    placeholder="Hallazgos, interpretación, plan inicial..."
                    @disabled($romBloqueado || $soloLectura)
                ></textarea>

                {{-- Solo mostrar esta nota si el motivo de bloqueo es “no finalizó el turno” --}}
                @if($romBloqueado && ! $soloLectura)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        No se puede editar/guardar evaluación (ROM) hasta que finalice el turno.
                    </p>
                @endif
            </div>

            {{-- ... tu bloque de ROM queda igual ... --}}

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Tip: definí los rangos normales en Catálogos clínicos → Movimientos.
                Si un movimiento no tiene rango, no se marcará “fuera de rango”.
            </p>

            {{-- Botones por paso (Evaluación) --}}
            <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Guardá este paso para desbloquear el siguiente.
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="irAlPaso({{ \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_ANAMNESIS }})"
                        class="rounded-lg px-4 py-2 text-sm font-semibold bg-gray-100 text-gray-700
                            hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                    >
                        ← Volver
                    </button>

                    <button
                        type="button"
                        wire:click="guardar"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        @disabled($romBloqueado || $soloLectura)
                        class="rounded-lg px-4 py-2 text-sm font-semibold
                            @if($romBloqueado || $soloLectura)
                                bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                            @else
                                bg-emerald-600 text-white hover:bg-emerald-700
                            @endif"
                    >
                        <span wire:loading.remove wire:target="guardar">Guardar evaluación</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>

                    <button
                        type="button"
                        wire:click="guardarEvaluacionYContinuar"
                        wire:loading.attr="disabled"
                        wire:target="guardarEvaluacionYContinuar"
                        @disabled($romBloqueado || $soloLectura)
                        class="rounded-lg px-4 py-2 text-sm font-semibold
                            @if($romBloqueado || $soloLectura)
                                bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                            @else
                                bg-indigo-600 text-white hover:bg-indigo-700
                            @endif"
                    >
                        <span wire:loading.remove wire:target="guardarEvaluacionYContinuar">Guardar y continuar →</span>
                        <span wire:loading wire:target="guardarEvaluacionYContinuar">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- =========================
     PASO 4 — Diagnóstico
     ========================= --}}
    @if($paso === \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_DIAGNOSTICO)
        @php
            $soloLectura = (bool) ($this->soloLectura ?? false);

            $tieneAlgo = (int) ($this->diagnosticoFuncionalId ?? 0) > 0
                || trim((string) ($this->diagnosticoNotas ?? '')) !== '';

            // ✅ A.3: siguiente paso (Cierre) bloqueado?
            $sigPasoDx = \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_CIERRE;
            $bloqueadoSigDx = $sigPasoDx > $max;
        @endphp

        <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Diagnóstico funcional</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Guardá este paso para desbloquear el Cierre.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    @if($soloLectura)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            Solo lectura
                        </span>
                    @elseif($tieneAlgo)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                            Cambios sin guardar
                        </span>
                    @endif
                </div>
            </div>

            {{-- 🔴 B.3 — Bloqueo unificado (solo lectura) --}}
            @if($soloLectura)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                            dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    Esta sección está en <strong>solo lectura</strong> porque la consulta fue finalizada
                    o el turno está cancelado/no asistió.
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-12 gap-4">
                {{-- Diagnóstico funcional --}}
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Diagnóstico funcional (principal)
                    </label>

                    <select
                        wire:model.live="diagnosticoFuncionalId"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                            @if($soloLectura) opacity-60 cursor-not-allowed @endif"
                        @disabled($soloLectura)
                    >
                        <option value="">— Seleccionar —</option>
                        @foreach(($this->diagnosticosOptions ?? []) as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Queda anclado al padecimiento de esta consulta (Opción A).
                    </p>

                    @if(! $this->padecimientoIdActual)
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                            Aún no hay padecimiento para esta consulta. Se creará automáticamente al guardar el diagnóstico.
                        </p>
                    @endif
                </div>

                {{-- Notas --}}
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Notas / Impresión clínica
                    </label>

                    <textarea
                        wire:model.debounce.600ms="diagnosticoNotas"
                        rows="4"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 bg-white dark:bg-transparent px-3 py-2 text-sm
                            @if($soloLectura) opacity-60 cursor-not-allowed @endif"
                        placeholder="Observaciones del diagnóstico, hipótesis funcional, factores contribuyentes, etc."
                        @disabled($soloLectura)
                    ></textarea>

                    {{-- ✅ Muestra ID + nombre + tipo --}}
                    @if($this->padecimientoIdActual)
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 space-y-1">
                            <span class="block">
                                Padecimiento (esta consulta):
                                <strong>#{{ $this->padecimientoIdActual }}</strong>
                                @if($this->padecimientoNombreActual)
                                    · <strong>{{ $this->padecimientoNombreActual }}</strong>
                                @endif
                            </span>

                            @if($this->padecimientoTipoNombreActual)
                                <span class="block">
                                    Tipo: <strong>{{ $this->padecimientoTipoNombreActual }}</strong>
                                    @if($this->padecimientoTipoIdActual)
                                        <span class="opacity-80">(#{{ $this->padecimientoTipoIdActual }})</span>
                                    @endif
                                </span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            {{-- 🧠 Resumen clínico del caso (siempre útil, incluso en solo lectura) --}}
            @if($this->padecimientoIdActual)
                <div class="mt-6 rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 px-4 py-3 text-sm">
                    <div class="font-semibold text-gray-700 dark:text-gray-200 mb-1">
                        Resumen clínico del caso
                    </div>

                    <ul class="space-y-1 text-gray-600 dark:text-gray-300">
                        <li>
                            <strong>Padecimiento:</strong>
                            {{ $this->padecimientoNombreActual ?? '—' }}
                        </li>

                        @if($this->padecimientoTipoNombreActual)
                            <li>
                                <strong>Tipo:</strong>
                                {{ $this->padecimientoTipoNombreActual }}
                            </li>
                        @endif

                        <li>
                            <strong>Diagnóstico principal:</strong>
                            @if($this->diagnosticoFuncionalId)
                                {{ $this->diagnosticosOptions[$this->diagnosticoFuncionalId] ?? '—' }}
                            @else
                                —
                            @endif
                        </li>
                    </ul>
                </div>
            @endif

            {{-- Botones por paso (Diagnóstico) --}}
            <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Guardá este paso para desbloquear el Cierre.
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        wire:click="irAlPaso({{ \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_EVALUACION }})"
                        class="rounded-lg px-4 py-2 text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                    >
                        ← Volver
                    </button>

                    <button
                        type="button"
                        wire:click="guardarDiagnostico"
                        wire:loading.attr="disabled"
                        wire:target="guardarDiagnostico"
                        @disabled($soloLectura)
                        class="rounded-lg px-4 py-2 text-sm font-semibold
                            @if($soloLectura)
                                bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                            @else
                                bg-emerald-600 text-white hover:bg-emerald-700
                            @endif"
                    >
                        <span wire:loading.remove wire:target="guardarDiagnostico">Guardar diagnóstico</span>
                        <span wire:loading wire:target="guardarDiagnostico">Guardando…</span>
                    </button>

                    <button
                        type="button"
                        wire:click="{{ $bloqueadoSigDx ? 'guardarDiagnostico' : "irAlPaso({$sigPasoDx})" }}"
                        wire:loading.attr="disabled"
                        wire:target="{{ $bloqueadoSigDx ? 'guardarDiagnostico' : 'irAlPaso' }}"
                        @disabled($soloLectura)
                        class="rounded-lg px-4 py-2 text-sm font-semibold
                            @if($soloLectura)
                                bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                            @else
                                bg-indigo-600 text-white hover:bg-indigo-700
                            @endif"
                    >
                        <span wire:loading.remove wire:target="{{ $bloqueadoSigDx ? 'guardarDiagnostico' : 'irAlPaso' }}">
                            {{ $bloqueadoSigDx ? 'Guardar y continuar →' : 'Continuar →' }}
                        </span>
                        <span wire:loading wire:target="{{ $bloqueadoSigDx ? 'guardarDiagnostico' : 'irAlPaso' }}">
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif


    {{-- =========================
        PASO 5 — Cierre
        ========================= --}}
    @if($paso === \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_CIERRE)
        @php
            $soloLectura = (bool) ($this->soloLectura ?? false);
        @endphp

        <div class="mt-6 rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Cierre</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Al finalizar se marca la consulta como finalizada y el turno como atendido.
                    </p>
                </div>

                @if($soloLectura)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        Solo lectura
                    </span>
                @endif
            </div>

            {{-- 🔴 B.3 — Bloqueo unificado (solo lectura) --}}
            @if($soloLectura)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                            dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    Esta sección está en <strong>solo lectura</strong> porque la consulta fue finalizada
                    o el turno está cancelado/no asistió.
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="irAlPaso({{ \App\Filament\Kinesiologa\Pages\EvaluacionInicial::PASO_DIAGNOSTICO }})"
                    class="rounded-lg px-4 py-2 text-sm font-semibold bg-gray-100 text-gray-700
                        hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                >
                    ← Volver
                </button>

                <button
                    type="button"
                    wire:click="finalizarConsulta"
                    wire:loading.attr="disabled"
                    wire:target="finalizarConsulta"
                    @disabled($soloLectura)
                    class="rounded-lg px-4 py-2 text-sm font-semibold
                        @if($soloLectura)
                            bg-gray-300 text-gray-700 cursor-not-allowed dark:bg-white/10 dark:text-gray-300
                        @else
                            bg-indigo-600 text-white hover:bg-indigo-700
                        @endif"
                >
                    <span wire:loading.remove wire:target="finalizarConsulta">Finalizar consulta</span>
                    <span wire:loading wire:target="finalizarConsulta">Finalizando…</span>
                </button>
            </div>

            @if(! $soloLectura)
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Importante: una vez finalizada, ya no se podrá modificar.
                </p>
            @endif
        </div>
    @endif
</x-filament-panels::page>













