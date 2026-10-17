// UTC sólo se usa para aritmética de fechas civiles; no para convertir horas de agenda.
export function sumarDias(fecha, dias) {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const civil = new Date(0);
    civil.setUTCFullYear(anio, mes - 1, dia + dias);
    civil.setUTCHours(0, 0, 0, 0);
    return civil.toISOString().slice(0, 10);
}

function fechaValida(fecha) {
    return /^\d{4}-\d{2}-\d{2}$/.test(fecha) && sumarDias(fecha, 0) === fecha;
}

function instanteValido(instante) {
    return typeof instante === 'string'
        && /^\d{4}-\d{2}-\d{2} ([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(instante)
        && fechaValida(instante.slice(0, 10));
}

function leerSlots(payload, ventana) {
    if (!payload || !Array.isArray(payload.slots)) {
        throw new Error('Respuesta de disponibilidad inválida.');
    }
    const inicios = new Set();
    return payload.slots.map((slot) => {
        if (!slot || !instanteValido(slot.inicio_at) || !instanteValido(slot.fin_at)
            || slot.fin_at <= slot.inicio_at || slot.inicio_at.slice(0, 10) < ventana.desde
            || slot.inicio_at.slice(0, 10) > ventana.hasta || inicios.has(slot.inicio_at)) {
            throw new Error('Intervalo de disponibilidad inválido.');
        }
        inicios.add(slot.inicio_at);
        return { inicio_at: slot.inicio_at, fin_at: slot.fin_at };
    });
}

export default function solicitarTurno(config, { fetchImpl = (...args) => globalThis.fetch(...args) } = {}) {
    let siguienteArchivoId = 0;
    let avisoActivo = false;
    let antesDeSalir = null;
    let secuenciaConsulta = 0;
    let controladorConsulta = null;
    let destruido = false;

    return {
        paso: 1,
        pasos: ['Motivo y documentación', 'Elegir profesional', 'Fecha y horario', 'Revisar y confirmar'],
        claveIdempotencia: config.uuid,
        profesionales: config.profesionales,
        limites: config.limites,
        maxMotivo: config.maxMotivo,
        motivo: '',
        archivos: [],
        errorMotivo: '',
        errorProfesional: '',
        kinesiologaId: null,
        slotSeleccionado: null,
        slotsDisponibles: [],
        ventana: { desde: config.fechaInicial, hasta: sumarDias(config.fechaInicial, 6) },
        estadoDisponibilidad: 'idle',
        errorDisponibilidad: '',

        init() {
            antesDeSalir = (event) => {
                if (this.hayDatosSignificativos) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            this.$watch('hayDatosSignificativos', () => this.actualizarAvisoAbandono());
        },

        destroy() {
            destruido = true;
            this.cancelarConsultaDisponibilidad();
            this.desactivarAvisoAbandono();
        },

        get longitudMotivo() {
            return [...this.motivo].length;
        },

        get archivosValidos() {
            return this.archivos.filter((entrada) => !entrada.error).map((entrada) => entrada.archivo);
        },

        get erroresArchivos() {
            return this.archivos.filter((entrada) => entrada.error);
        },

        get totalBytes() {
            return this.archivos.reduce((total, entrada) => total + entrada.archivo.size, 0);
        },

        get erroresGeneralesArchivos() {
            const errores = [];
            if (this.archivos.length > this.limites.maxArchivos) {
                errores.push(`Podés seleccionar hasta ${this.limites.maxArchivos} archivos.`);
            }
            if (this.totalBytes > this.limites.maxBytesTotal) {
                errores.push(`Los archivos superan el máximo total de ${this.tamanioLegible(this.limites.maxBytesTotal)}.`);
            }
            return errores;
        },

        get hayDatosSignificativos() {
            return this.motivo.trim() !== '' || this.archivos.length > 0
                || this.kinesiologaId !== null || this.slotSeleccionado !== null;
        },

        get profesionalSeleccionada() {
            return this.profesionales.find((profesional) => profesional.id === this.kinesiologaId) ?? null;
        },

        agregarArchivos(event) {
            for (const archivo of Array.from(event.target.files ?? [])) {
                const extension = archivo.name.split('.').pop().toLowerCase();
                let error = '';
                if (!this.limites.extensionesPermitidas.includes(extension)
                    || (archivo.type && !this.limites.mimePermitidos.includes(archivo.type.toLowerCase()))) {
                    error = 'Elegí un archivo con formato permitido.';
                } else if (archivo.size > this.limites.maxBytesPorArchivo) {
                    error = `El archivo supera ${this.tamanioLegible(this.limites.maxBytesPorArchivo)}.`;
                }
                this.archivos.push({ id: ++siguienteArchivoId, archivo, error });
            }
            event.target.value = '';
        },

        quitarArchivo(id) {
            this.archivos = this.archivos.filter((entrada) => entrada.id !== id);
        },

        seleccionarProfesional(id) {
            const elegida = Number(id);
            if (!this.profesionales.some((profesional) => profesional.id === elegida)) {
                return;
            }
            if (this.kinesiologaId !== elegida) {
                this.invalidarDisponibilidad();
                this.kinesiologaId = elegida;
            }
            this.errorProfesional = '';
        },

        async siguiente() {
            if (this.paso === 1) {
                this.errorMotivo = this.motivo.trim() === '' ? 'Ingresá el motivo de consulta.'
                    : (this.longitudMotivo > this.maxMotivo ? `El motivo no puede superar ${this.maxMotivo} caracteres.` : '');
                if (this.errorMotivo) {
                    this.$refs.motivo.focus();
                    return;
                }
                if (this.erroresArchivos.length || this.erroresGeneralesArchivos.length) {
                    this.$refs.documentacion.focus();
                    return;
                }
                this.paso = 2;
            } else if (this.paso === 2) {
                if (!this.profesionalSeleccionada) {
                    this.errorProfesional = 'Seleccioná una profesional para continuar.';
                    this.$refs.profesionales.focus();
                    return;
                }
                this.paso = 3;
                this.enfocarPaso();
                return this.consultarDisponibilidad();
            } else if (this.paso === 3 && this.puedeRevisar) {
                this.paso = 4;
            } else {
                return;
            }
            this.enfocarPaso();
        },

        anterior() {
            if (this.paso === 3) {
                this.invalidarDisponibilidad();
            }
            this.paso = Math.max(1, this.paso - 1);
            this.enfocarPaso();
        },

        enfocarPaso() {
            this.$nextTick(() => this.$refs[`tituloPaso${this.paso}`]?.focus());
        },

        get puedeRevisar() {
            return this.estadoDisponibilidad === 'success' && this.slotSeleccionado !== null
                && this.slotsDisponibles.some((slot) => slot.inicio_at === this.slotSeleccionado.inicio_at
                    && slot.fin_at === this.slotSeleccionado.fin_at);
        },

        get gruposSlots() {
            const grupos = new Map();
            for (const slot of this.slotsDisponibles) {
                const fecha = slot.inicio_at.slice(0, 10);
                if (!grupos.has(fecha)) grupos.set(fecha, { fecha, slots: [] });
                grupos.get(fecha).slots.push(slot);
            }
            return [...grupos.values()];
        },

        fechaLegible(fecha) {
            const [anio, mes, dia] = fecha.split('-').map(Number);
            const civil = new Date(0);
            civil.setUTCFullYear(anio, mes - 1, dia);
            return new Intl.DateTimeFormat('es-AR', { timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(civil);
        },

        horaLegible(instante) {
            return instante.slice(11, 16);
        },

        cancelarConsultaDisponibilidad() {
            secuenciaConsulta++;
            controladorConsulta?.abort();
            controladorConsulta = null;
        },

        invalidarDisponibilidad() {
            this.cancelarConsultaDisponibilidad();
            this.slotSeleccionado = null;
            this.slotsDisponibles = [];
            this.estadoDisponibilidad = 'idle';
            this.errorDisponibilidad = '';
        },

        cambiarVentana(direccion) {
            if (this.paso !== 3 || ![-1, 1].includes(direccion)) return;
            const desde = sumarDias(this.ventana.desde, direccion * 7);
            if (desde < config.fechaInicial) return;
            this.ventana = { desde, hasta: sumarDias(desde, 6) };
            return this.consultarDisponibilidad();
        },

        seleccionarSlot(inicioAt) {
            if (this.paso !== 3 || this.estadoDisponibilidad !== 'success') return;
            const slot = this.slotsDisponibles.find((candidato) => candidato.inicio_at === inicioAt);
            if (slot) this.slotSeleccionado = { inicio_at: slot.inicio_at, fin_at: slot.fin_at };
        },

        async consultarDisponibilidad() {
            if (destruido || this.paso !== 3 || !this.profesionalSeleccionada) return;
            this.invalidarDisponibilidad();
            const requestId = secuenciaConsulta;
            const profesionalId = this.kinesiologaId;
            const ventana = { ...this.ventana };
            const vigente = () => !destruido && requestId === secuenciaConsulta && this.paso === 3
                && this.kinesiologaId === profesionalId && this.ventana.desde === ventana.desde && this.ventana.hasta === ventana.hasta;
            controladorConsulta = typeof AbortController === 'undefined' ? null : new AbortController();
            const opciones = { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' };
            if (controladorConsulta) opciones.signal = controladorConsulta.signal;
            const url = new URL(config.urlDisponibilidad);
            url.search = new URLSearchParams({ kinesiologa_id: profesionalId, desde: ventana.desde, hasta: ventana.hasta });
            this.estadoDisponibilidad = 'loading';
            try {
                const response = await fetchImpl(url.toString(), opciones);
                if (!vigente()) return;
                const payload = await response.json();
                if (!vigente()) return;
                if (response.status === 409 && payload?.code === 'profesional_no_elegible') {
                    this.invalidarDisponibilidad();
                    this.kinesiologaId = null;
                    this.errorProfesional = 'La profesional ya no está disponible para atender. Elegí otra profesional.';
                    this.paso = 2;
                    this.enfocarPaso();
                    return;
                }
                if (response.status !== 200) throw new Error('No se pudo consultar disponibilidad.');
                this.slotsDisponibles = leerSlots(payload, ventana);
                this.estadoDisponibilidad = this.slotsDisponibles.length ? 'success' : 'empty';
            } catch (error) {
                if (!vigente() || error?.name === 'AbortError') return;
                this.estadoDisponibilidad = 'error';
                this.errorDisponibilidad = 'No pudimos consultar los horarios. Intentá nuevamente.';
            } finally {
                if (vigente()) {
                    if (this.estadoDisponibilidad === 'loading') this.estadoDisponibilidad = 'idle';
                    controladorConsulta = null;
                }
            }
        },

        tamanioLegible(bytes) {
            return `${Number((bytes / (1024 * 1024)).toFixed(2))} MiB`;
        },

        actualizarAvisoAbandono() {
            if (this.hayDatosSignificativos && !avisoActivo) {
                window.addEventListener('beforeunload', antesDeSalir);
                avisoActivo = true;
            } else if (!this.hayDatosSignificativos) {
                this.desactivarAvisoAbandono();
            }
        },

        desactivarAvisoAbandono() {
            if (avisoActivo) {
                window.removeEventListener('beforeunload', antesDeSalir);
                avisoActivo = false;
            }
        },

        async volverAMisTurnos() {
            if (this.hayDatosSignificativos) {
                const confirmado = window.Swal
                    ? (await window.Swal.fire({
                        icon: 'warning',
                        title: '¿Salir de la solicitud?',
                        text: 'Se perderán el motivo, los archivos y las selecciones de esta página.',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, salir',
                        cancelButtonText: 'Continuar aquí',
                        reverseButtons: true,
                        focusCancel: true,
                    })).isConfirmed
                    : window.confirm('¿Salir y descartar los datos de esta solicitud?');
                if (!confirmado) {
                    return;
                }
            }
            this.desactivarAvisoAbandono();
            window.location.assign(config.urlMisTurnos);
        },
    };
}
