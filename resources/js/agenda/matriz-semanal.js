const DIAS_SEMANA = [
    { numero: 1, nombre: 'Lunes' },
    { numero: 2, nombre: 'Martes' },
    { numero: 3, nombre: 'Miércoles' },
    { numero: 4, nombre: 'Jueves' },
    { numero: 5, nombre: 'Viernes' },
    { numero: 6, nombre: 'Sábado' },
    { numero: 7, nombre: 'Domingo' },
];

const minutosDesdeHora = (hora) => {
    if (typeof hora !== 'string' || !/^([01]\d|2[0-3]):[0-5]\d$/.test(hora)) {
        return null;
    }

    const [horas, minutos] = hora.split(':').map(Number);

    return (horas * 60) + minutos;
};

const fechaLegible = (fecha) => {
    if (typeof fecha !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        return fecha || 'sin definir';
    }

    const [anio, mes, dia] = fecha.split('-');

    return `${dia}/${mes}/${anio}`;
};

export default function matrizSemanalAgenda(config = {}) {
    let contadorClaves = 0;

    const erroresBackend = config.errores && typeof config.errores === 'object'
        ? config.errores
        : {};

    const primerError = (indice, campo) => {
        const mensajes = erroresBackend[`franjas.${indice}.${campo}`];

        return Array.isArray(mensajes) ? (mensajes[0] || '') : '';
    };

    const normalizarFranja = (franja = {}, indice = 0) => ({
        key: `franja-${contadorClaves++}`,
        diaSemana: Number(franja.dia_semana) || 1,
        horaInicio: typeof franja.hora_inicio === 'string'
            ? franja.hora_inicio.slice(0, 5)
            : '',
        horaFin: typeof franja.hora_fin === 'string'
            ? franja.hora_fin.slice(0, 5)
            : '',
        erroresServidor: {
            fila: Array.isArray(erroresBackend[`franjas.${indice}`])
                ? (erroresBackend[`franjas.${indice}`][0] || '')
                : '',
            dia_semana: primerError(indice, 'dia_semana'),
            hora_inicio: primerError(indice, 'hora_inicio'),
            hora_fin: primerError(indice, 'hora_fin'),
        },
    });

    return {
        dias: DIAS_SEMANA,
        franjas: Array.isArray(config.franjas)
            ? config.franjas.map(normalizarFranja)
            : [],
        duracionTurnoMinutos: config.duracionTurnoMinutos ?? '',
        vigenciaDesde: config.vigenciaDesde || '',
        vigenciaHasta: config.vigenciaHasta || '',
        vigenciaHastaDerivada: config.vigenciaHastaDerivada || '',
        hoy: config.hoy || '',
        permiteInicioHoy: config.permiteInicioHoy === true,
        confirmacion: config.confirmacion || {},
        enviando: false,

        init() {
            if (config.tieneErroresServidor) {
                this.$nextTick(() => document.getElementById('resumen-errores')?.focus());
            }
        },

        get cantidadFranjas() {
            return this.franjas.length;
        },

        get cantidadDiasAtencion() {
            return new Set(this.franjas.map((franja) => franja.diaSemana)).size;
        },

        get duracionValida() {
            const duracion = Number(this.duracionTurnoMinutos);

            return Number.isInteger(duracion)
                && duracion >= 15
                && duracion <= 180
                && duracion % 5 === 0;
        },

        get periodoValido() {
            return !this.vigenciaHasta
                || !this.vigenciaDesde
                || this.vigenciaHasta >= this.vigenciaDesde;
        },

        get cantidadIncompatibilidadesDuracion() {
            return this.incompatibilidadesDuracion.length;
        },

        get incompatibilidadesDuracion() {
            if (!this.duracionValida) {
                return [];
            }

            return this.franjas.filter((franja) => {
                const duracion = this.duracionFranja(franja);

                return duracion !== null && duracion < Number(this.duracionTurnoMinutos);
            });
        },

        get tieneErroresLocales() {
            return !this.duracionValida
                || !this.periodoValido
                || this.franjas.length === 0
                || this.franjas.some((franja) => this.erroresFranja(franja).length > 0);
        },

        get resumenMatriz() {
            const dias = this.cantidadDiasAtencion;
            const franjas = this.cantidadFranjas;

            return `${dias} ${dias === 1 ? 'día' : 'días'} con atención, ${franjas} ${franjas === 1 ? 'franja' : 'franjas'} en total.`;
        },

        get mensajeCompatibilidad() {
            if (!this.duracionValida) {
                return 'Ingresá una duración válida para comprobar la compatibilidad de las franjas.';
            }

            if (this.cantidadIncompatibilidadesDuracion === 0) {
                return 'Todas las franjas pueden conservarse con la nueva duración.';
            }

            const cantidad = this.cantidadIncompatibilidadesDuracion;

            return `${cantidad} ${cantidad === 1 ? 'franja necesita' : 'franjas necesitan'} corrección por su duración.`;
        },

        get iniciaHoy() {
            return this.permiteInicioHoy
                && this.vigenciaDesde !== ''
                && this.vigenciaDesde === this.hoy;
        },

        franjasDia(dia) {
            return this.franjas.filter((franja) => franja.diaSemana === dia);
        },

        indiceFranja(franja) {
            return this.franjas.indexOf(franja);
        },

        posicionFranjaDia(franja) {
            return this.franjasDia(franja.diaSemana).indexOf(franja) + 1;
        },

        agregarFranja(dia) {
            const franja = normalizarFranja({
                dia_semana: dia,
                hora_inicio: '',
                hora_fin: '',
            }, -1);

            this.franjas.push(franja);
            this.$nextTick(() => document.getElementById(this.idCampo(franja, 'inicio'))?.focus());
        },

        quitarFranja(key) {
            this.franjas = this.franjas.filter((franja) => franja.key !== key);
        },

        limpiarErrorServidor(franja, campo) {
            franja.erroresServidor[campo] = '';
        },

        errorCampoServidor(franja, campo) {
            return franja.erroresServidor[campo] || '';
        },

        descripcionFranja(franja) {
            const dia = this.dias.find((item) => item.numero === franja.diaSemana)?.nombre || 'Día';
            const intervalo = franja.horaInicio && franja.horaFin
                ? `${franja.horaInicio}–${franja.horaFin}`
                : 'horario incompleto';
            const duracion = this.duracionFranja(franja);

            return `${dia}, ${intervalo}: ${duracion ?? 0} min; requiere al menos ${this.duracionTurnoMinutos} min.`;
        },

        duracionFranja(franja) {
            const inicio = minutosDesdeHora(franja.horaInicio);
            const fin = minutosDesdeHora(franja.horaFin);

            if (inicio === null || fin === null || fin <= inicio) {
                return null;
            }

            return fin - inicio;
        },

        erroresFranja(franja) {
            const errores = [];
            const dia = this.dias.find((item) => item.numero === franja.diaSemana)?.nombre || 'Día';
            const inicio = minutosDesdeHora(franja.horaInicio);
            const fin = minutosDesdeHora(franja.horaFin);

            if (!franja.horaInicio) {
                errores.push(`${dia}: la hora de inicio es obligatoria.`);
            }

            if (!franja.horaFin) {
                errores.push(`${dia}: la hora de fin es obligatoria.`);
            }

            if (inicio !== null && fin !== null) {
                if (fin <= inicio) {
                    errores.push(`${dia}: la hora de fin debe ser posterior a la hora de inicio.`);
                } else if (this.duracionValida && (fin - inicio) < Number(this.duracionTurnoMinutos)) {
                    errores.push(`${dia}: esta franja dura ${fin - inicio} min y debe durar al menos ${this.duracionTurnoMinutos} min.`);
                }

                const superpuesta = this.franjasDia(franja.diaSemana).find((otra) => {
                    if (otra.key === franja.key) {
                        return false;
                    }

                    const otroInicio = minutosDesdeHora(otra.horaInicio);
                    const otroFin = minutosDesdeHora(otra.horaFin);

                    return otroInicio !== null
                        && otroFin !== null
                        && otroFin > otroInicio
                        && inicio < otroFin
                        && fin > otroInicio;
                });

                if (superpuesta) {
                    const desde = inicio > minutosDesdeHora(superpuesta.horaInicio)
                        ? franja.horaInicio
                        : superpuesta.horaInicio;
                    const hasta = fin < minutosDesdeHora(superpuesta.horaFin)
                        ? franja.horaFin
                        : superpuesta.horaFin;

                    errores.push(`${dia}: estas franjas se superponen entre las ${desde} y las ${hasta}.`);
                }
            }

            return errores;
        },

        filaInvalida(franja) {
            return this.erroresFranja(franja).length > 0
                || Object.values(franja.erroresServidor).some(Boolean);
        },

        idFila(franja) {
            return `agenda-${franja.key}`;
        },

        idCampo(franja, campo) {
            return `${this.idFila(franja)}-${campo}`;
        },

        idErroresFila(franja) {
            return `${this.idFila(franja)}-errores`;
        },

        textoConfirmacion() {
            const fin = this.vigenciaHasta
                || this.vigenciaHastaDerivada
                || '';
            const periodo = fin
                ? `desde ${fechaLegible(this.vigenciaDesde)} hasta ${fechaLegible(fin)}`
                : `desde ${fechaLegible(this.vigenciaDesde)}, sin fecha de fin`;

            return `Vigencia ${periodo}. Duración: ${this.duracionTurnoMinutos} minutos. Matriz: ${this.cantidadFranjas} ${this.cantidadFranjas === 1 ? 'franja' : 'franjas'}.`;
        },

        confirmarEnvio(event) {
            if (this.tieneErroresLocales) {
                event.preventDefault();
                document.getElementById('resumen-validacion-local')?.focus();

                return;
            }

            if (this.enviando || !window.Swal) {
                return;
            }

            event.preventDefault();
            const form = event.currentTarget;

            window.Swal.fire({
                icon: 'question',
                title: this.confirmacion.titulo || '¿Confirmar configuración?',
                text: this.textoConfirmacion(),
                showCancelButton: true,
                confirmButtonText: this.confirmacion.boton || 'Confirmar guardado',
                cancelButtonText: 'Volver a revisar',
                reverseButtons: true,
                focusCancel: true,
            }).then((resultado) => {
                if (!resultado.isConfirmed) {
                    return;
                }

                this.enviando = true;
                this.$nextTick(() => form.requestSubmit());
            });
        },
    };
}
