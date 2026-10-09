const FECHA_ISO = /^\d{4}-\d{2}-\d{2}$/;
const HORA = /^([01]\d|2[0-3]):[0-5]\d$/;
const FECHA_HORA_CANONICA = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::\d{2})?/;

export const sumarDiasCalendario = (fecha, cantidad) => {
    if (!FECHA_ISO.test(fecha) || !Number.isInteger(cantidad)) {
        return '';
    }

    const [anio, mes, dia] = fecha.split('-').map(Number);
    const resultado = new Date(Date.UTC(anio, mes - 1, dia + cantidad));

    return resultado.toISOString().slice(0, 10);
};

export const componerIntervaloCompleto = (fechaInicio, fechaFinInclusiva) => {
    if (!FECHA_ISO.test(fechaInicio) || !FECHA_ISO.test(fechaFinInclusiva)) {
        return { inicioAt: '', finAt: '' };
    }

    return {
        inicioAt: `${fechaInicio} 00:00:00`,
        finAt: `${sumarDiasCalendario(fechaFinInclusiva, 1)} 00:00:00`,
    };
};

export const componerIntervaloParcial = (fechaInicio, horaInicio, fechaFin, horaFin) => {
    if (!FECHA_ISO.test(fechaInicio)
        || !FECHA_ISO.test(fechaFin)
        || !HORA.test(horaInicio)
        || !HORA.test(horaFin)) {
        return { inicioAt: '', finAt: '' };
    }

    return {
        inicioAt: `${fechaInicio} ${horaInicio}:00`,
        finAt: `${fechaFin} ${horaFin}:00`,
    };
};

const partesFechaHora = (valor) => {
    const coincidencia = typeof valor === 'string'
        ? valor.match(FECHA_HORA_CANONICA)
        : null;

    return coincidencia
        ? { fecha: coincidencia[1], hora: coincidencia[2] }
        : { fecha: '', hora: '' };
};

export const reconstruirCaptura = (inicioAt, finAt) => {
    const inicio = partesFechaHora(inicioAt);
    const fin = partesFechaHora(finAt);
    const esRangoCompleto = inicio.fecha
        && fin.fecha
        && inicio.hora === '00:00'
        && fin.hora === '00:00'
        && fin.fecha > inicio.fecha;

    if (esRangoCompleto) {
        return {
            modo: 'completo',
            fechaInicio: inicio.fecha,
            fechaFin: sumarDiasCalendario(fin.fecha, -1),
            horaInicio: '',
            horaFin: '',
        };
    }

    return {
        modo: inicio.fecha || fin.fecha ? 'parcial' : 'completo',
        fechaInicio: inicio.fecha,
        fechaFin: fin.fecha,
        horaInicio: inicio.hora,
        horaFin: fin.hora,
    };
};

const fechaLegible = (fecha) => {
    if (!FECHA_ISO.test(fecha)) {
        return fecha || 'sin definir';
    }

    const [anio, mes, dia] = fecha.split('-');

    return `${dia}/${mes}/${anio}`;
};

export const resumirIntervalo = (captura) => {
    if (!captura.fechaInicio || !captura.fechaFin) {
        return 'Completá las fechas para obtener un resumen del intervalo.';
    }

    if (captura.modo === 'completo') {
        if (captura.fechaInicio === captura.fechaFin) {
            return `Todo el día del ${fechaLegible(captura.fechaInicio)}.`;
        }

        return `Días completos desde ${fechaLegible(captura.fechaInicio)} hasta ${fechaLegible(captura.fechaFin)}, inclusive.`;
    }

    if (!captura.horaInicio || !captura.horaFin) {
        return 'Completá las horas para obtener un resumen del intervalo.';
    }

    return `Desde ${fechaLegible(captura.fechaInicio)} ${captura.horaInicio} hasta ${fechaLegible(captura.fechaFin)} ${captura.horaFin}, de forma continua.`;
};

const cantidadTexto = (cantidad, singular, plural) => `${cantidad} ${cantidad === 1 ? singular : plural}`;

export default function bloqueoAgendaForm(config = {}) {
    const capturaInicial = reconstruirCaptura(config.inicioAt || '', config.finAt || '');

    return {
        etapa: config.tieneRevision ? 'revision' : 'captura',
        modo: capturaInicial.modo,
        fechaInicio: capturaInicial.fechaInicio,
        fechaFin: capturaInicial.fechaFin,
        horaInicio: capturaInicial.horaInicio,
        horaFin: capturaInicial.horaFin,
        motivo: config.motivo || '',
        inicioAt: config.inicioAt || '',
        finAt: config.finAt || '',
        erroresLocales: [],
        dirty: config.tieneDatosIniciales === true,
        enviando: false,
        urlCancelar: config.urlCancelar || '',
        cantidadTurnos: Number(config.cantidadTurnos || 0),
        cantidadBloqueos: Number(config.cantidadBloqueos || 0),

        init() {
            if (!this.inicioAt || !this.finAt) {
                this.sincronizarCanonicos();
            }

            this.$nextTick(() => {
                if (config.tieneErrores) {
                    document.getElementById('resumen-errores-bloqueo')?.focus();
                } else if (this.etapa === 'revision') {
                    const destino = config.tieneMensajeStale
                        ? document.getElementById('mensaje-revision-obsoleta')
                        : document.getElementById('titulo-revision-bloqueo');
                    destino?.focus();
                }
            });
        },

        get resumenIntervalo() {
            return resumirIntervalo(this);
        },

        get intervaloValido() {
            return this.inicioAt !== ''
                && this.finAt !== ''
                && this.inicioAt < this.finAt;
        },

        get capturaCompleta() {
            const fechasCompletas = this.fechaInicio !== '' && this.fechaFin !== '';
            const horasCompletas = this.modo === 'completo'
                || (this.horaInicio !== '' && this.horaFin !== '');

            return fechasCompletas && horasCompletas && this.motivo.trim() !== '';
        },

        cambiarModo(modo) {
            this.modo = modo;
            this.dirty = true;
            this.erroresLocales = [];
            this.sincronizarCanonicos();
        },

        campoModificado() {
            this.dirty = true;
            this.erroresLocales = [];
            this.sincronizarCanonicos();
        },

        aplicarPreset(preset) {
            this.motivo = preset;
            this.campoModificado();
            this.$nextTick(() => document.getElementById('motivo')?.focus());
        },

        sincronizarCanonicos() {
            const intervalo = this.modo === 'completo'
                ? componerIntervaloCompleto(this.fechaInicio, this.fechaFin)
                : componerIntervaloParcial(
                    this.fechaInicio,
                    this.horaInicio,
                    this.fechaFin,
                    this.horaFin,
                );

            this.inicioAt = intervalo.inicioAt;
            this.finAt = intervalo.finAt;
        },

        validarCaptura() {
            this.sincronizarCanonicos();
            const errores = [];

            if (!this.fechaInicio) {
                errores.push('La fecha inicial es obligatoria.');
            }

            if (!this.fechaFin) {
                errores.push('La fecha final es obligatoria.');
            }

            if (this.fechaInicio && this.fechaFin && this.fechaFin < this.fechaInicio) {
                errores.push('La fecha final no puede ser anterior a la fecha inicial.');
            }

            if (this.modo === 'parcial' && (!this.horaInicio || !this.horaFin)) {
                errores.push('Las horas inicial y final son obligatorias para un intervalo parcial.');
            }

            if (this.inicioAt && this.finAt && !this.intervaloValido) {
                errores.push('La finalización debe ser posterior al inicio.');
            }

            if (!this.motivo.trim()) {
                errores.push('El motivo es obligatorio.');
            }

            if (this.motivo.length > 500) {
                errores.push('El motivo no puede superar 500 caracteres.');
            }

            this.erroresLocales = errores;

            return errores.length === 0;
        },

        revisar(event) {
            if (this.enviando || !this.validarCaptura()) {
                event.preventDefault();
                this.$nextTick(() => document.getElementById('resumen-validacion-bloqueo')?.focus());

                return;
            }

            this.enviando = true;
        },

        volverAEditar() {
            this.etapa = 'captura';
            this.dirty = true;
            this.enviando = false;
            this.$nextTick(() => document.getElementById('selector-modo-bloqueo')?.focus());
        },

        textoConfirmacion() {
            const impactos = `${cantidadTexto(this.cantidadTurnos, 'turno afectado', 'turnos afectados')} y ${cantidadTexto(this.cantidadBloqueos, 'bloqueo superpuesto', 'bloqueos superpuestos')}.`;
            const avisoTurnos = this.cantidadTurnos > 0
                ? ' Los turnos no serán modificados automáticamente.'
                : '';

            return `${this.resumenIntervalo} Motivo: ${this.motivo}. ${impactos}${avisoTurnos}`;
        },

        confirmarRegistro(event) {
            event.preventDefault();

            if (this.enviando) {
                return;
            }

            const form = event.currentTarget;

            if (!window.Swal) {
                this.enviando = true;
                form.submit();

                return;
            }

            window.Swal.fire({
                icon: this.cantidadTurnos > 0 || this.cantidadBloqueos > 0 ? 'warning' : 'question',
                title: 'Confirmar registro del bloqueo',
                text: this.textoConfirmacion(),
                showCancelButton: true,
                confirmButtonText: 'Confirmar registro',
                cancelButtonText: 'Volver a revisar',
                reverseButtons: true,
                focusCancel: true,
            }).then((resultado) => {
                if (!resultado.isConfirmed) {
                    return;
                }

                this.enviando = true;
                form.submit();
            });
        },

        cancelar() {
            if (!this.dirty || !window.Swal) {
                window.location.assign(this.urlCancelar);

                return;
            }

            window.Swal.fire({
                icon: 'warning',
                title: '¿Descartar el bloqueo?',
                text: 'Los datos ingresados y la revisión actual se perderán.',
                showCancelButton: true,
                confirmButtonText: 'Sí, descartar',
                cancelButtonText: 'Continuar aquí',
                reverseButtons: true,
                focusCancel: true,
            }).then((resultado) => {
                if (resultado.isConfirmed) {
                    window.location.assign(this.urlCancelar);
                }
            });
        },
    };
}
