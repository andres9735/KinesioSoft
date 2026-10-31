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
        volverAlListado: false,
        mensaje: '',
        motivo: '',

        actualizar() {
            if (!this.ocupado) navegar(this.volverAlListado ? (config.urlVolver ?? config.urlDetalle) : config.urlDetalle);
        },

        enfocarMensaje() {
            if (this.mensaje && typeof this.$nextTick === 'function') {
                this.$nextTick(() => this.$refs?.resultado?.focus());
            }
        },

        async solicitar(operacion) {
            if (this.ocupado || this.necesitaActualizar || !['confirmar', 'cancelar'].includes(operacion)) return;
            const swal = obtenerSwal();
            if (!swal || typeof swal.fire !== 'function') {
                this.mensaje = 'No pudimos abrir la confirmación. Actualizá la página antes de continuar.';
                this.enfocarMensaje();
                return;
            }
            this.ocupado = true;
            this.mensaje = '';
            const cancelar = operacion === 'cancelar';
            let temporizador;
            let solicitudIniciada = false;
            let resultadoConocido = false;
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
                solicitudIniciada = true;
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
                // Estos rechazos son conocidos incluso si el middleware devuelve HTML.
                const rechazosHttp = {
                    401: 'Tu sesión ya no está disponible. Actualizá el detalle e iniciá sesión para continuar.',
                    403: 'No tenés permiso para operar sobre este turno. Volvé a Mis turnos.',
                    404: 'Este turno ya no está disponible. Volvé a Mis turnos para consultar tus turnos.',
                    419: 'La sesión o el formulario venció. Actualizá el detalle antes de volver a confirmar la operación.',
                };
                if (Object.hasOwn(rechazosHttp, respuesta.status)) {
                    this.enviando = false;
                    this.necesitaActualizar = true;
                    this.volverAlListado = [403, 404].includes(respuesta.status);
                    this.mensaje = rechazosHttp[respuesta.status];
                    return;
                }
                let datos;
                try {
                    datos = await respuesta.json();
                } catch (error) {
                    if (![409, 422].includes(respuesta.status)) throw error;
                }
                if ([409, 422].includes(respuesta.status) && (!datos || typeof datos !== 'object')) datos = {};
                globalThis.clearTimeout(temporizador);
                // El transporte terminó; el diálogo de resultado no es procesamiento.
                this.enviando = false;
                const codigo = cancelar ? 'turno_cancelado' : 'asistencia_confirmada';
                if (respuesta.status === 200 && datos.code === codigo && typeof datos.es_replay === 'boolean'
                    && typeof datos.message === 'string' && datos.message.length > 0) {
                    resultadoConocido = true;
                    this.necesitaActualizar = true;
                    this.mensaje = datos.message;
                    await swal.fire({ icon: datos.es_replay ? 'info' : 'success', title: datos.es_replay ? 'Operación ya realizada' : 'Operación completada', text: datos.message });
                    navegar(config.urlDetalle);
                } else if ([409, 422].includes(respuesta.status)) {
                    resultadoConocido = true;
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
                            : 'Los datos enviados no son válidos. Vamos a consultar el detalle actualizado antes de continuar.');
                    await swal.fire({ icon: 'warning', title: 'Consultá el estado actual', text: this.mensaje });
                    navegar(config.urlDetalle);
                } else {
                    this.necesitaActualizar = true;
                    this.mensaje = 'No pudimos confirmar el resultado. Consultá el estado actual antes de realizar otra operación.';
                }
            } catch {
                this.necesitaActualizar = true;
                if (!resultadoConocido) {
                    this.mensaje = solicitudIniciada
                        ? 'No pudimos confirmar el resultado. Consultá el estado actual; no repetimos la operación automáticamente.'
                        : 'No pudimos completar la confirmación visual. No se envió la operación. Actualizá el detalle antes de continuar.';
                }
            } finally {
                globalThis.clearTimeout(temporizador);
                this.enviando = false;
                this.ocupado = false;
                this.enfocarMensaje();
            }
        },
    };
}
