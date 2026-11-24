<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Confirmación de revisión</h1></x-slot>
    <section class="mx-auto max-w-4xl space-y-4 px-4 py-8" x-data="confirmacionRevision(@js([
        'respuestaInicial' => true, 'status' => match ($tipo) { 'autorizacion' => 403, 'version', 'conflicto' => 409, 'minimo', 'confirmacion' => 422, default => 503 },
        'expedienteUrl' => route('historia-clinica.desde-turno', $turnoId),
        'revisionUrl' => route('historia-clinica.desde-turno', $turnoId)]))">
        <div hidden data-resultado-revision="{{ json_encode(['estado' => 'error', 'tipo' => $tipo], JSON_THROW_ON_ERROR) }}"></div>
        <div role="alert" class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
            @switch($tipo)
                @case('autorizacion')
                    <h2 class="text-lg font-semibold">Sin autorización para confirmar</h2><p>Se requiere la habilitación, los permisos clínicos y la asignación vigente al turno.</p>@break
                @case('version')
                    <h2 class="text-lg font-semibold">Conflicto de versión o contexto</h2><p>El expediente, la declaración o los datos de base cambiaron después de preparar la propuesta. Recuperá el expediente y generá una nueva vista previa.</p>@break
                @case('minimo')
                    <h2 class="text-lg font-semibold">Información clínica insuficiente o inválida</h2><p>Revisá los dictámenes, relevamientos y procedencias mediante una nueva vista previa antes de confirmar.</p>@break
                @case('confirmacion')
                    <h2 class="text-lg font-semibold">Confirmación explícita pendiente o formulario inválido</h2><p>La confirmación requiere aceptar el resultado de la vista previa. Si cambiaste contenido, generá una nueva vista previa.</p>@break
                @case('guardado')
                    <h2 class="text-lg font-semibold">No se pudo determinar el resultado</h2><p>Consultá el expediente para comprobar su situación antes de intentar una nueva confirmación. Esta solicitud no se reenviará automáticamente.</p>@break
                @default
                    <h2 class="text-lg font-semibold">Propuesta incompatible o ya procesada con otra confirmación</h2><p>No se puede incorporar esta solicitud. Recuperá el expediente para comprobar su situación actual antes de continuar.</p>
            @endswitch
            @if ($tipo !== 'guardado')<p>No se registró una nueva incorporación mediante esta solicitud.</p>@endif
        </div>
        <a data-recuperar-revision class="inline-flex min-h-11 items-center font-semibold text-indigo-700 underline" href="{{ route('historia-clinica.desde-turno', $turnoId) }}">Volver al expediente</a>
    </section>
</x-app-layout>
