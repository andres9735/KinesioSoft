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
        enviando: false,
        consolidado: false,
        redireccionando: false,
        confirmacionBloqueada: false,
        resultadoIncierto: false,
        mensajeConfirmacion: '',
        erroresServidor: {},
        profesionalesNoElegibles: [],

        get bloqueado() {
            return this.enviando || this.consolidado || this.redireccionando;
        },

        get puedeConfirmar() {
            return !this.bloqueado && !this.confirmacionBloqueada && this.paso === 4
                && this.motivo.trim() !== '' && this.longitudMotivo <= this.maxMotivo
                && !this.erroresServidor.motivo_solicitud && !this.erroresServidor.adjuntos
                && !this.erroresArchivos.length && !this.erroresGeneralesArchivos.length
                && this.profesionalSeleccionada !== null && this.puedeRevisar;
        },

        init() {
            antesDeSalir = (event) => {
                if (this.hayDatosSignificativos && !this.consolidado && !this.redireccionando) {
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
            return this.archivos.filter((entrada) => !entrada.error && !entrada.errorServidor).map((entrada) => entrada.archivo);
        },

        get erroresArchivos() {
            return this.archivos.filter((entrada) => entrada.error || entrada.errorServidor);
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
            if (this.bloqueado) return;
            delete this.erroresServidor.adjuntos;
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
            if (this.bloqueado) return;
            this.archivos = this.archivos.filter((entrada) => entrada.id !== id);
            delete this.erroresServidor.adjuntos;
        },

        corregirMotivo() {
            if (this.bloqueado) return;
            this.errorMotivo = '';
            delete this.erroresServidor.motivo_solicitud;
        },

        seleccionarProfesional(id) {
            if (this.bloqueado || this.profesionalesNoElegibles.includes(Number(id))) return;
            const elegida = Number(id);
            if (!this.profesionales.some((profesional) => profesional.id === elegida)) {
                return;
            }
            if (this.kinesiologaId !== elegida) {
                this.invalidarDisponibilidad();
                this.kinesiologaId = elegida;
            }
            this.errorProfesional = '';
            delete this.erroresServidor.kinesiologa_id;
        },

        async siguiente() {
            if (this.bloqueado) return;
            if (this.paso === 1) {
                this.errorMotivo = this.motivo.trim() === '' ? 'Ingresá el motivo de consulta.'
                    : (this.longitudMotivo > this.maxMotivo ? `El motivo no puede superar ${this.maxMotivo} caracteres.` : '');
                if (this.errorMotivo || this.erroresServidor.motivo_solicitud) {
                    this.$refs.motivo.focus();
                    return;
                }
                if (this.erroresArchivos.length || this.erroresGeneralesArchivos.length || this.erroresServidor.adjuntos) {
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
            if (this.bloqueado) return;
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
            if (this.bloqueado) return;
            if (this.paso !== 3 || ![-1, 1].includes(direccion)) return;
            const desde = sumarDias(this.ventana.desde, direccion * 7);
            if (desde < config.fechaInicial) return;
            this.ventana = { desde, hasta: sumarDias(desde, 6) };
            return this.consultarDisponibilidad();
        },

        seleccionarSlot(inicioAt) {
            if (this.bloqueado) return;
            if (this.paso !== 3 || this.estadoDisponibilidad !== 'success') return;
            const slot = this.slotsDisponibles.find((candidato) => candidato.inicio_at === inicioAt);
            if (slot) {
                this.slotSeleccionado = { inicio_at: slot.inicio_at, fin_at: slot.fin_at };
                delete this.erroresServidor.inicio_at;
            }
        },

        async consultarDisponibilidad() {
            if (destruido || this.bloqueado || this.paso !== 3 || !this.profesionalSeleccionada) return;
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
                    this.invalidarProfesional(true);
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

        invalidarProfesional(obsoleta = false) {
            if (obsoleta && this.kinesiologaId !== null) this.profesionalesNoElegibles.push(this.kinesiologaId);
            this.invalidarDisponibilidad();
            this.kinesiologaId = null;
            this.errorProfesional = 'La profesional ya no está disponible para atender. Elegí otra profesional.';
            this.paso = 2;
            this.enfocarPaso();
        },

        construirConfirmacion(archivos) {
            const datos = new FormData();
            datos.append('clave_idempotencia', this.claveIdempotencia);
            datos.append('motivo_solicitud', this.motivo);
            datos.append('kinesiologa_id', this.kinesiologaId);
            datos.append('inicio_at', this.slotSeleccionado.inicio_at);
            for (const entrada of archivos) datos.append('adjuntos[]', entrada.archivo);
            return datos;
        },

        async confirmarTurno() {
            if (destruido || !this.puedeConfirmar) return;
            this.enviando = true;
            this.resultadoIncierto = false;
            this.mensajeConfirmacion = '';
            // El índice Laravel corresponde a esta tentativa, no al input nativo.
            const archivosEnviados = [...this.archivos];
            let refrescar = false;
            try {
                const response = await fetchImpl(config.urlConfirmacion, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: this.construirConfirmacion(archivosEnviados),
                });
                const payload = await response.json().catch(() => null);
                if (destruido) return;
                if ([200, 201].includes(response.status)) {
                    if (!payload || !Number.isSafeInteger(payload.turno_id) || payload.turno_id <= 0
                        || typeof payload.es_replay !== 'boolean' || payload.es_replay !== (response.status === 200)
                        || typeof payload.redirect_url !== 'string' || !payload.redirect_url.trim()) {
                        throw new Error('Resultado no confirmado.');
                    }
                    await this.finalizarConfirmacion(payload.redirect_url);
                } else if (response.status === 409 && payload?.code === 'primera_consulta_activa') {
                    this.redireccionando = true;
                    await this.mostrarFeedback({ icon: 'info', title: 'Ya tenés una Primera consulta activa',
                        text: 'Podés consultar tus turnos en Mis turnos.', confirmButtonText: 'Ir a Mis turnos' });
                    this.desactivarAvisoAbandono();
                    window.location.assign(config.urlMisTurnos);
                } else if (response.status === 409 && payload?.code === 'profesional_no_elegible') {
                    this.invalidarProfesional(true);
                } else if (response.status === 409 && payload?.code === 'slot_no_disponible') {
                    this.invalidarDisponibilidad();
                    this.paso = 3;
                    this.mensajeConfirmacion = 'Ese horario acaba de dejar de estar disponible. Elegí otro horario.';
                    this.enfocarPaso();
                    refrescar = true;
                } else if (response.status === 409) {
                    this.confirmacionBloqueada = true;
                    this.mensajeConfirmacion = 'No pudimos continuar con esta solicitud. Volvé a Mis turnos para iniciar un nuevo flujo.';
                } else if ([401, 403, 419].includes(response.status)) {
                    this.confirmacionBloqueada = true;
                    this.mensajeConfirmacion = 'Tu sesión venció o ya no permite confirmar. Volvé a ingresar al sistema. Los datos permanecen en esta página mientras siga abierta.';
                } else if (response.status === 422 && payload?.errors && typeof payload.errors === 'object' && !Array.isArray(payload.errors)) {
                    refrescar = this.aplicarErroresServidor(payload.errors, archivosEnviados);
                } else {
                    throw new Error('Resultado no confirmado.');
                }
            } catch {
                if (!destruido && !this.consolidado && !this.redireccionando) {
                    this.resultadoIncierto = true;
                    this.mensajeConfirmacion = 'No pudimos confirmar el resultado. Tu turno podría haberse registrado. Reintentá con los mismos datos para comprobarlo de forma segura.';
                }
            } finally {
                this.enviando = false;
            }
            if (refrescar) await this.consultarDisponibilidad();
        },

        aplicarErroresServidor(errores, archivosEnviados) {
            let paso = 4;
            let refrescar = false;
            let reconocido = false;
            for (const [campo, mensajes] of Object.entries(errores)) {
                if (!Array.isArray(mensajes) || !mensajes.every((mensaje) => typeof mensaje === 'string') || !mensajes.length) continue;
                const mensaje = mensajes.join(' ');
                if (campo === 'clave_idempotencia') {
                    this.confirmacionBloqueada = true;
                    this.mensajeConfirmacion = 'No pudimos continuar con esta solicitud. Volvé a Mis turnos para iniciar un nuevo flujo.';
                    reconocido = true;
                } else if (campo === 'motivo_solicitud' || campo === 'adjuntos') {
                    this.erroresServidor[campo] = mensaje;
                    paso = 1;
                    reconocido = true;
                } else if (/^adjuntos\.\d+$/.test(campo)) {
                    const enviada = archivosEnviados[Number(campo.split('.')[1])];
                    const entrada = this.archivos.find((actual) => actual.id === enviada?.id);
                    if (entrada) {
                        entrada.errorServidor = mensaje;
                        paso = 1;
                        reconocido = true;
                    }
                } else if (campo === 'kinesiologa_id') {
                    this.invalidarProfesional();
                    this.erroresServidor[campo] = mensaje;
                    paso = Math.min(paso, 2);
                    reconocido = true;
                } else if (campo === 'inicio_at') {
                    this.invalidarDisponibilidad();
                    this.erroresServidor[campo] = mensaje;
                    paso = Math.min(paso, 3);
                    refrescar = true;
                    reconocido = true;
                }
            }
            if (!reconocido) {
                this.confirmacionBloqueada = true;
                this.mensajeConfirmacion = 'No pudimos validar esta solicitud. Volvé a Mis turnos para iniciar un nuevo flujo.';
            }
            this.paso = paso;
            this.enfocarPaso();
            return refrescar && paso === 3;
        },

        async mostrarFeedback(opciones) {
            try {
                if (window.Swal) await window.Swal.fire({ ...opciones, allowOutsideClick: false, allowEscapeKey: false });
            } catch {
                // Una anomalía del feedback no cambia el resultado consolidado ni impide navegar.
            }
        },

        async finalizarConfirmacion(redirectUrl) {
            this.consolidado = true;
            this.desactivarAvisoAbandono();
            await this.mostrarFeedback({ icon: 'success', title: 'Turno registrado correctamente',
                text: `Primera consulta con ${this.profesionalSeleccionada.nombre}. ${this.fechaLegible(this.slotSeleccionado.inicio_at.slice(0, 10))}, ${this.horaLegible(this.slotSeleccionado.inicio_at)} – ${this.horaLegible(this.slotSeleccionado.fin_at)}. Estado: Pendiente.`,
                confirmButtonText: 'Entendido' });
            window.location.assign(redirectUrl);
        },

        tamanioLegible(bytes) {
            return `${Number((bytes / (1024 * 1024)).toFixed(2))} MiB`;
        },

        actualizarAvisoAbandono() {
            if (this.consolidado || this.redireccionando) {
                this.desactivarAvisoAbandono();
            } else if (this.hayDatosSignificativos && !avisoActivo) {
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
            if (this.bloqueado) return;
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
