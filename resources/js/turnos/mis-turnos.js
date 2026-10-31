export default function misTurnos(config, {
    fetchImpl = (...args) => globalThis.fetch(...args),
    obtenerSwal = () => globalThis.Swal,
    navegar = (url) => globalThis.location.assign(url),
    timeoutMs = 30000,
} = {}) {
    return {
        ocupado: false,
        enviando: false,
        necesitaActualizar: false,
        mensaje: '',
        motivo: '',

        actualizar() {
            if (!this.ocupado) navegar(config.urlDetalle);
        },

        async solicitar(operacion) {
            if (this.ocupado || this.necesitaActualizar || !['confirmar', 'cancelar'].includes(operacion)) return;
            const swal = obtenerSwal();
            if (!swal || typeof swal.fire !== 'function') {
                this.mensaje = 'No pudimos abrir la confirmación. Actualizá la página antes de continuar.';
                return;
            }
            this.ocupado = true;
            this.mensaje = '';
            const cancelar = operacion === 'cancelar';
            let temporizador;
            try {
                const decision = await swal.fire({
                    title: cancelar ? '¿Cancelar este turno?' : '¿Confirmar tu asistencia?',
                    text: config.resumen + (cancelar
                        ? ' La cancelación ordinaria es definitiva y no garantiza que otra persona reciba el horario.'
                        : ' Expresás tu intención de asistir; no se crea una nueva reserva.'),
                    icon: cancelar ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonText: cancelar ? 'Sí, cancelar turno' : 'Sí, confirmar asistencia',
                    cancelButtonText: 'Volver sin cambios',
                    focusCancel: true,
                    returnFocus: true,
                    ...(cancelar ? { input: 'textarea', inputLabel: 'Motivo de cancelación (opcional)', inputValue: this.motivo } : {}),
                });
                if (decision.isConfirmed !== true) return;
                if (cancelar) this.motivo = String(decision.value ?? '');
                this.enviando = true;
                // Un timeout de transporte no prueba rollback: consultar, nunca reenviar.
                const controlador = new AbortController();
                temporizador = globalThis.setTimeout(() => controlador.abort(), timeoutMs);
                const respuesta = await fetchImpl(cancelar ? config.urlCancelar : config.urlConfirmar, {
                    method: 'POST',
                    signal: controlador.signal,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken, 'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ referencia_programacion: config.referencia, ...(cancelar ? { motivo: this.motivo } : {}) }),
                });
                const datos = await respuesta.json();
                globalThis.clearTimeout(temporizador);
                const codigo = cancelar ? 'turno_cancelado' : 'asistencia_confirmada';
                if (respuesta.status === 200 && datos.code === codigo && typeof datos.es_replay === 'boolean'
                    && typeof datos.message === 'string' && datos.message.length > 0) {
                    this.necesitaActualizar = true;
                    this.mensaje = datos.message;
                    await swal.fire({ icon: datos.es_replay ? 'info' : 'success', title: datos.es_replay ? 'Operación ya realizada' : 'Operación completada', text: datos.message });
                    navegar(config.urlDetalle);
                } else if ([409, 422].includes(respuesta.status)) {
                    this.necesitaActualizar = true;
                    const rechazos = {
                        fuera_de_plazo: 'La cancelación online requiere al menos 12 horas de anticipación. Comunicate con el consultorio.',
                        inicio_no_futuro: 'El turno ya alcanzó su horario de inicio. Consultá su estado actual.',
                        estado_no_permitido: 'El estado actual del turno no permite esta operación.',
                        tipo_no_permitido: 'El tipo de turno no permite esta operación.',
                    };
                    this.mensaje = respuesta.status === 409
                        ? 'La programación cambió. Vamos a consultar el detalle actualizado.'
                        : (Object.hasOwn(rechazos, datos.code) ? rechazos[datos.code]
                            : 'La operación fue rechazada. Vamos a consultar las condiciones actuales del turno.');
                    await swal.fire({ icon: 'warning', title: 'Consultá el estado actual', text: this.mensaje });
                    navegar(config.urlDetalle);
                } else {
                    this.necesitaActualizar = true;
                    this.mensaje = 'No pudimos confirmar el resultado. Consultá el estado actual antes de realizar otra operación.';
                }
            } catch {
                this.necesitaActualizar = true;
                this.mensaje = 'No pudimos confirmar el resultado. Consultá el estado actual; no repetimos la operación automáticamente.';
            } finally {
                globalThis.clearTimeout(temporizador);
                this.enviando = false;
                this.ocupado = false;
            }
        },
    };
}
