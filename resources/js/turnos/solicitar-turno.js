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

function fechaCivil(fecha) {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const civil = new Date(0);
    civil.setUTCFullYear(anio, mes - 1, dia);
    civil.setUTCHours(0, 0, 0, 0);
    return civil;
}

function leerDias(payload, ventana) {
    if (!payload || payload.desde !== ventana.desde || payload.hasta !== ventana.hasta || !Array.isArray(payload.dias)) {
        throw new Error('Respuesta de disponibilidad inválida.');
    }
    let anterior = '';
    return payload.dias.map((dia) => {
        if (!dia || typeof dia.fecha !== 'string' || !fechaValida(dia.fecha) || dia.fecha <= anterior
            || dia.fecha < ventana.desde || dia.fecha > ventana.hasta || !Array.isArray(dia.horarios) || !dia.horarios.length) {
            throw new Error('Fecha de disponibilidad inválida.');
        }
        anterior = dia.fecha;
        let previo = '';
        return { fecha: dia.fecha, horarios: dia.horarios.map((horario) => {
            if (!horario || !instanteValido(horario.inicio_at) || horario.inicio_at.slice(0, 10) !== dia.fecha || horario.inicio_at <= previo) {
                throw new Error('Inicio de disponibilidad inválido.');
            }
            previo = horario.inicio_at;
            return { inicio_at: horario.inicio_at };
        }) };
    });
}

function leerProfesionales(payload, inicio) {
    if (!payload || payload.inicio_at !== inicio || !Array.isArray(payload.profesionales)) {
        throw new Error('Respuesta de profesionales inválida.');
    }
    const ids = new Set();
    return payload.profesionales.map((profesional) => {
        if (!profesional || !Number.isSafeInteger(profesional.kinesiologa_id) || profesional.kinesiologa_id <= 0
            || ids.has(profesional.kinesiologa_id) || typeof profesional.nombre !== 'string' || !profesional.nombre.trim()
            || !instanteValido(profesional.fin_at) || profesional.fin_at <= inicio) {
            throw new Error('Profesional disponible inválida.');
        }
        ids.add(profesional.kinesiologa_id);
        return { kinesiologa_id: profesional.kinesiologa_id, nombre: profesional.nombre, fin_at: profesional.fin_at };
    });
}

export default function solicitarTurno(config, { fetchImpl = (...args) => globalThis.fetch(...args) } = {}) {
    let siguienteArchivoId = 0;
    let avisoActivo = false;
    let antesDeSalir = null;
    let secuenciaConsulta = 0;
    let controladorConsulta = null;
    let secuenciaProfesionales = 0;
    let controladorProfesionales = null;
    let destruido = false;
    let inicializado = false;
    let secuenciaRecuperacion = 0;
    let controladorRecuperacion = null;

    async function consultarJson(urlBase, parametros, signal, vigente) {
        const url = new URL(urlBase);
        url.search = new URLSearchParams(parametros);
        const opciones = { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' };
        if (signal) opciones.signal = signal;
        const response = await fetchImpl(url.toString(), opciones);
        if (!vigente()) return;
        if (response.status !== 200) {
            const error = new Error('No se pudo consultar la disponibilidad.');
            error.status = response.status;
            throw error;
        }
        const payload = await response.json();
        if (vigente()) return payload;
    }

    return {
        paso: 1,
        pasos: ['Fecha y horario', 'Elegir profesional', 'Motivo y documentación', 'Revisar y confirmar'],
        claveIdempotencia: config.uuid,
        profesionales: [],
        inicioProfesionales: null,
        estadoProfesionales: 'idle',
        errorConsultaProfesionales: '',
        tipoErrorProfesionales: null,
        limites: config.limites,
        maxMotivo: config.maxMotivo,
        motivo: '',
        archivos: [],
        erroresSeleccionArchivos: [],
        errorMotivo: '',
        errorProfesional: '',
        kinesiologaId: null,
        fechaSeleccionada: null,
        inicioSeleccionado: null,
        diasDisponibles: [],
        ventanaConsultada: null,
        ventana: { desde: config.fechaInicial, hasta: sumarDias(config.fechaInicial, 6) },
        estadoDisponibilidad: 'idle',
        errorDisponibilidad: '',
        tipoErrorDisponibilidad: null,
        enviando: false,
        consolidado: false,
        redireccionando: false,
        confirmacionBloqueada: false,
        resultadoIncierto: false,
        mensajeConfirmacion: '',
        erroresServidor: {},
        recuperacion: null,
        tipoRecuperacion: null,

        get recuperando() {
            return this.recuperacion?.estado === 'loading';
        },

        get detalleRecuperacion() {
            return {
                R1: 'Conservamos la fecha y la hora elegidas. Seleccioná otra profesional disponible o cambiá la fecha y el horario.',
                R2: 'Conservamos la fecha elegida. Seleccioná otro horario disponible para continuar.',
                R3: 'La disponibilidad se actualizó. Elegí otra fecha disponible para continuar.',
            }[this.tipoRecuperacion] ?? '';
        },

        get bloqueado() {
            return this.enviando || this.consolidado || this.redireccionando || this.recuperacion !== null;
        },

        get puedeConfirmar() {
            return !this.bloqueado && !this.confirmacionBloqueada && this.paso === 4
                && this.motivo.trim() !== '' && this.longitudMotivo <= this.maxMotivo
                && !this.erroresServidor.motivo_solicitud && !this.erroresServidor.adjuntos
                && !this.erroresArchivos.length && !this.erroresGeneralesArchivos.length
                && !this.erroresDescripciones.length
                && this.profesionalSeleccionada !== null && this.puedeRevisar;
        },

        init() {
            if (inicializado) return;
            inicializado = true;
            antesDeSalir = (event) => {
                if (this.hayDatosSignificativos && !this.consolidado && !this.redireccionando) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            this.$watch('hayDatosSignificativos', () => this.actualizarAvisoAbandono());
            this.$nextTick(() => this.consultarDisponibilidad());
        },

        destroy() {
            destruido = true;
            this.cancelarConsultaDisponibilidad();
            this.cancelarConsultaProfesionales();
            this.cancelarRecuperacion();
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

        get erroresDescripciones() {
            return this.archivos.filter((entrada) => entrada.errorDescripcionServidor || this.errorDescripcion(entrada));
        },

        errorDescripcion(entrada) {
            if (typeof entrada.descripcion !== 'string' || !entrada.descripcion.trim()) return 'Ingresá una descripción para este adjunto.';
            return [...entrada.descripcion.trim()].length > 500 ? 'La descripción no puede superar 500 caracteres.' : '';
        },

        revisarDescripcion(id) {
            const entrada = this.archivos.find((actual) => actual.id === id);
            if (!this.bloqueado && entrada) entrada.descripcionRevisada = true;
        },

        get puedeAvanzarPaso3() {
            return this.puedeRevisar && this.motivo.trim() !== '' && this.longitudMotivo <= this.maxMotivo
                && !this.erroresServidor.motivo_solicitud && !this.erroresServidor.adjuntos
                && !this.erroresArchivos.length && !this.erroresDescripciones.length && !this.erroresGeneralesArchivos.length;
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
                || this.fechaSeleccionada !== null || this.inicioSeleccionado !== null || this.kinesiologaId !== null;
        },

        get profesionalSeleccionada() {
            if (this.estadoProfesionales !== 'success' || this.inicioProfesionales !== this.inicioSeleccionado) return null;
            return this.profesionales.find((profesional) => profesional.kinesiologa_id === this.kinesiologaId) ?? null;
        },

        get intervaloSeleccionado() {
            return this.profesionalSeleccionada && this.inicioSeleccionado
                ? { inicio_at: this.inicioSeleccionado, fin_at: this.profesionalSeleccionada.fin_at } : null;
        },

        agregarArchivos(event) {
            if (this.bloqueado) return;
            delete this.erroresServidor.adjuntos;
            this.erroresSeleccionArchivos = [];
            for (const archivo of Array.from(event.target.files ?? [])) {
                const entrada = this.nuevaEntradaArchivo(archivo);
                if (entrada) this.archivos.push(entrada);
            }
            event.target.value = '';
        },

        nuevaEntradaArchivo(archivo) {
            const extension = archivo.name.split('.').pop().toLowerCase();
            let error = '';
            if (!this.limites.extensionesPermitidas.includes(extension)
                || (archivo.type && !this.limites.mimePermitidos.includes(archivo.type.toLowerCase()))) {
                error = `Formato no permitido. Sólo se admiten archivos ${this.limites.extensionesPermitidas.map((extension) => extension.toUpperCase()).join(', ')}.`;
            } else if (archivo.size > this.limites.maxBytesPorArchivo) {
                error = `El archivo supera ${this.tamanioLegible(this.limites.maxBytesPorArchivo)}.`;
            }
            const id = ++siguienteArchivoId;
            if (error) {
                this.erroresSeleccionArchivos.push({ id, nombre: archivo.name, error });
                return null;
            }
            return { id, archivo, descripcion: '', descripcionRevisada: false, error: '' };
        },

        descartarErrorSeleccion(id) {
            if (!this.bloqueado) this.erroresSeleccionArchivos = this.erroresSeleccionArchivos.filter((error) => error.id !== id);
        },

        reemplazarArchivo(id, archivo) {
            if (this.bloqueado) return;
            const indice = this.archivos.findIndex((entrada) => entrada.id === id);
            if (indice < 0) return;
            this.erroresSeleccionArchivos = [];
            const entrada = this.nuevaEntradaArchivo(archivo);
            if (entrada) {
                this.archivos.splice(indice, 1, entrada);
                delete this.erroresServidor.adjuntos;
            }
        },

        corregirDescripcion(id, descripcion) {
            if (this.bloqueado) return;
            const entrada = this.archivos.find((actual) => actual.id === id);
            if (entrada) {
                entrada.descripcion = descripcion;
                delete entrada.errorDescripcionServidor;
            }
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
            if (this.bloqueado || !this.puedeElegirProfesional || this.estadoProfesionales !== 'success'
                || this.inicioProfesionales !== this.inicioSeleccionado) return;
            const elegida = Number(id);
            if (!this.profesionales.some((profesional) => profesional.kinesiologa_id === elegida)) {
                return;
            }
            this.kinesiologaId = elegida;
            this.limpiarAvisoRecuperacion();
            this.errorProfesional = '';
            delete this.erroresServidor.kinesiologa_id;
        },

        async siguiente() {
            if (this.bloqueado) return;
            if (this.paso === 1) {
                if (!this.puedeElegirProfesional) {
                    this.enfocarPaso('errorInicio');
                    return;
                }
                this.paso = 2;
                this.enfocarPaso();
                if (this.inicioProfesionales !== this.inicioSeleccionado) return this.cargarProfesionalesDisponibles();
                return;
            } else if (this.paso === 2) {
                if (!this.profesionalSeleccionada) {
                    this.errorProfesional = 'Seleccioná una profesional para continuar.';
                    this.enfocarPaso('errorProfesional');
                    return;
                }
                this.paso = 3;
            } else if (this.paso === 3) {
                this.archivos.forEach((entrada) => { entrada.descripcionRevisada = true; });
                this.errorMotivo = this.motivo.trim() === '' ? 'Ingresá el motivo de consulta.'
                    : (this.longitudMotivo > this.maxMotivo ? `El motivo no puede superar ${this.maxMotivo} caracteres.` : '');
                if (this.errorMotivo || this.erroresServidor.motivo_solicitud) {
                    this.$refs.motivo.focus();
                    return;
                }
                if (this.erroresArchivos.length || this.erroresDescripciones.length || this.erroresGeneralesArchivos.length || this.erroresServidor.adjuntos) {
                    this.$refs.documentacion.focus();
                    return;
                }
                if (!this.puedeRevisar) return;
                this.paso = 4;
            } else {
                return;
            }
            this.enfocarPaso();
        },

        anterior() {
            if (this.bloqueado) return;
            this.paso = Math.max(1, this.paso - 1);
            this.enfocarPaso();
        },

        editarDesdeRevision(destino) {
            if (this.bloqueado || this.paso !== 4 || ![1, 2, 3].includes(destino)) return;
            this.paso = destino;
            this.enfocarPaso();
        },

        enfocarPaso(...destinos) {
            const paso = this.paso;
            this.$nextTick(() => {
                // El navegador debe aplicar también el disabled heredado del fieldset al finalizar un POST.
                const alRenderizar = globalThis.requestAnimationFrame ?? ((callback) => callback());
                alRenderizar(() => {
                    if (this.paso !== paso) return;
                    const referencias = [...destinos, `tituloPaso${paso}`];
                    const visible = referencias.map((nombre) => this.$refs[nombre])
                        .find((elemento) => elemento?.getClientRects?.().length);
                    visible?.focus();
                });
            });
        },

        get puedeRevisar() {
            return this.puedeElegirProfesional && this.profesionalSeleccionada !== null;
        },

        get puedeElegirProfesional() {
            return this.estadoDisponibilidad === 'success' && this.inicioSeleccionado !== null
                && this.horariosDisponibles.some((horario) => horario.inicio_at === this.inicioSeleccionado);
        },

        get horariosDisponibles() {
            return this.diasDisponibles.find((dia) => dia.fecha === this.fechaSeleccionada)?.horarios ?? [];
        },

        estadoFecha(fecha) {
            if (fecha < config.fechaInicial) return 'pasada';
            if (!['success', 'empty'].includes(this.estadoDisponibilidad) || !this.ventanaConsultada
                || fecha < this.ventanaConsultada.desde || fecha > this.ventanaConsultada.hasta) return 'no_consultada';
            if (!this.diasDisponibles.some((dia) => dia.fecha === fecha)) return 'sin_disponibilidad';
            return fecha === this.fechaSeleccionada ? 'seleccionada' : 'disponible';
        },

        etiquetaFecha(fecha) {
            const estados = { pasada: 'Fecha pasada', no_consultada: 'No consultada', sin_disponibilidad: 'Sin disponibilidad',
                disponible: 'Disponible', seleccionada: 'Seleccionada' };
            return `${this.fechaLegible(fecha)}. ${estados[this.estadoFecha(fecha)]}`;
        },

        get fechasCalendario() {
            const primero = this.ventana.desde.slice(0, 7) + '-01';
            const siguienteMes = fechaCivil(primero);
            siguienteMes.setUTCMonth(siguienteMes.getUTCMonth() + 1);
            const ultimoMes = sumarDias(siguienteMes.toISOString().slice(0, 10), -1);
            const ultimo = ultimoMes > this.ventana.hasta ? ultimoMes : this.ventana.hasta;
            const desde = sumarDias(primero, -((fechaCivil(primero).getUTCDay() + 6) % 7));
            const hasta = sumarDias(ultimo, (7 - fechaCivil(ultimo).getUTCDay()) % 7);
            const fechas = [];
            for (let fecha = desde; fecha <= hasta; fecha = sumarDias(fecha, 1)) {
                fechas.push({ fecha, dia: Number(fecha.slice(8)), estado: this.estadoFecha(fecha) });
            }
            return fechas;
        },

        get mesCalendario() {
            const formato = new Intl.DateTimeFormat('es-AR', { timeZone: 'UTC', month: 'long', year: 'numeric' });
            const desde = formato.format(fechaCivil(this.ventana.desde));
            return this.ventana.desde.slice(0, 7) === this.ventana.hasta.slice(0, 7)
                ? desde : `${desde} – ${formato.format(fechaCivil(this.ventana.hasta))}`;
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

        inicialesProfesional(nombre) {
            return nombre.trim().split(/\s+/u).slice(0, 2).map((parte) => [...parte][0].toUpperCase()).join('');
        },

        duracionLegible(inicio, fin) {
            // Sólo presentación: aritmética civil sin interpretar zona ni recalcular el final backend.
            const segundosCiviles = (instante) => {
                const [hora, minuto, segundo] = instante.slice(11).split(':').map(Number);
                return fechaCivil(instante.slice(0, 10)).getTime() / 1000 + hora * 3600 + minuto * 60 + segundo;
            };
            const minutos = (segundosCiviles(fin) - segundosCiviles(inicio)) / 60;
            return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 }).format(minutos) + ' min';
        },

        cancelarConsultaDisponibilidad() {
            secuenciaConsulta++;
            controladorConsulta?.abort();
            controladorConsulta = null;
        },

        invalidarDisponibilidad() {
            this.cancelarConsultaDisponibilidad();
            this.invalidarInicio();
            this.diasDisponibles = [];
            this.ventanaConsultada = null;
            this.estadoDisponibilidad = 'idle';
            this.errorDisponibilidad = '';
            this.tipoErrorDisponibilidad = null;
        },

        cancelarConsultaProfesionales() {
            secuenciaProfesionales++;
            controladorProfesionales?.abort();
            controladorProfesionales = null;
        },

        limpiarProfesional() {
            this.cancelarConsultaProfesionales();
            this.kinesiologaId = null;
            this.profesionales = [];
            this.inicioProfesionales = null;
            this.estadoProfesionales = 'idle';
            this.errorConsultaProfesionales = '';
            this.tipoErrorProfesionales = null;
            this.errorProfesional = '';
        },

        invalidarInicio() {
            this.inicioSeleccionado = null;
            this.limpiarProfesional();
        },

        limpiarAvisoRecuperacion() {
            if (!this.tipoRecuperacion) return;
            this.tipoRecuperacion = null;
            this.mensajeConfirmacion = '';
        },

        seleccionarFecha(fecha) {
            if (this.bloqueado || this.estadoDisponibilidad !== 'success'
                || fecha < config.fechaInicial
                || !this.diasDisponibles.some((dia) => dia.fecha === fecha)) return;
            if (this.fechaSeleccionada !== fecha) {
                this.invalidarInicio();
                this.fechaSeleccionada = fecha;
                this.limpiarAvisoRecuperacion();
            }
        },

        seleccionarInicio(inicio) {
            if (this.bloqueado || this.estadoDisponibilidad !== 'success'
                || !this.horariosDisponibles.some((horario) => horario.inicio_at === inicio)) return;
            if (this.inicioSeleccionado !== inicio) {
                this.limpiarProfesional();
                this.inicioSeleccionado = inicio;
                this.limpiarAvisoRecuperacion();
            }
            delete this.erroresServidor.inicio_at;
        },

        cambiarVentana(direccion) {
            if (this.bloqueado) return;
            if (this.paso !== 1 || ![-1, 1].includes(direccion)) return;
            const desde = sumarDias(this.ventana.desde, direccion * 7);
            if (desde < config.fechaInicial) return;
            this.ventana = { desde, hasta: sumarDias(desde, 6) };
            this.fechaSeleccionada = null;
            this.limpiarAvisoRecuperacion();
            return this.consultarDisponibilidad();
        },

        async consultarDisponibilidad() {
            if (destruido || this.bloqueado || this.paso !== 1) return;
            const focoEnError = this.$refs.errorDisponibilidad?.contains?.(globalThis.document?.activeElement) ?? false;
            this.invalidarDisponibilidad();
            if (focoEnError) this.enfocarPaso();
            const requestId = secuenciaConsulta;
            const ventana = { ...this.ventana };
            const vigente = () => !destruido && requestId === secuenciaConsulta
                && this.ventana.desde === ventana.desde && this.ventana.hasta === ventana.hasta;
            controladorConsulta = typeof AbortController === 'undefined' ? null : new AbortController();
            this.estadoDisponibilidad = 'loading';
            try {
                const payload = await consultarJson(config.urlDisponibilidad, { desde: ventana.desde, hasta: ventana.hasta }, controladorConsulta?.signal, vigente);
                if (!vigente()) return;
                this.diasDisponibles = leerDias(payload, ventana);
                this.ventanaConsultada = ventana;
                this.estadoDisponibilidad = this.diasDisponibles.length ? 'success' : 'empty';
                if (!this.diasDisponibles.some((dia) => dia.fecha === this.fechaSeleccionada)) this.fechaSeleccionada = null;
            } catch (error) {
                if (!vigente() || error?.name === 'AbortError') return;
                this.estadoDisponibilidad = 'error';
                this.tipoErrorDisponibilidad = error?.status === 422 ? 'validacion' : 'tecnico';
                this.errorDisponibilidad = 'No pudimos consultar los horarios. Intentá nuevamente.';
                this.enfocarPaso('errorDisponibilidad');
            } finally {
                if (vigente()) {
                    if (this.estadoDisponibilidad === 'loading') this.estadoDisponibilidad = 'idle';
                    controladorConsulta = null;
                }
            }
        },

        async cargarProfesionalesDisponibles() {
            if (destruido || this.bloqueado || !this.puedeElegirProfesional) return;
            this.limpiarProfesional();
            const inicio = this.inicioSeleccionado;
            const requestId = secuenciaProfesionales;
            const vigente = () => !destruido && requestId === secuenciaProfesionales && this.inicioSeleccionado === inicio;
            controladorProfesionales = typeof AbortController === 'undefined' ? null : new AbortController();
            this.estadoProfesionales = 'loading';
            try {
                const payload = await consultarJson(config.urlProfesionalesDisponibles, { inicio_at: inicio }, controladorProfesionales?.signal, vigente);
                if (!vigente()) return;
                this.profesionales = leerProfesionales(payload, inicio);
                this.inicioProfesionales = inicio;
                this.estadoProfesionales = this.profesionales.length ? 'success' : 'empty';
            } catch (error) {
                if (!vigente() || error?.name === 'AbortError') return;
                this.estadoProfesionales = 'error';
                this.tipoErrorProfesionales = error?.status === 422 ? 'validacion' : 'tecnico';
                this.errorConsultaProfesionales = 'No pudimos consultar las profesionales. Intentá nuevamente.';
                this.enfocarPaso('errorProfesionales');
            } finally {
                if (vigente()) {
                    if (this.estadoProfesionales === 'loading') this.estadoProfesionales = 'idle';
                    controladorProfesionales = null;
                }
            }
        },

        invalidarProfesional() {
            this.limpiarProfesional();
            this.errorProfesional = 'La profesional ya no está disponible para atender. Elegí otra profesional.';
            this.paso = 2;
            this.enfocarPaso('errorProfesional');
        },

        cancelarRecuperacion() {
            secuenciaRecuperacion++;
            controladorRecuperacion?.abort();
            controladorRecuperacion = null;
        },

        async iniciarRecuperacion(contexto) {
            if (destruido) return;
            this.cancelarRecuperacion();
            this.cancelarConsultaDisponibilidad();
            this.limpiarProfesional();
            const ventana = contexto.ventana.desde <= contexto.fecha && contexto.ventana.hasta >= contexto.fecha
                ? { ...contexto.ventana } : { desde: contexto.fecha, hasta: sumarDias(contexto.fecha, 6) };
            this.recuperacion = { ...contexto, ventana, fase: 'profesionales', estado: 'idle' };
            this.fechaSeleccionada = contexto.fecha;
            this.inicioSeleccionado = contexto.inicio;
            this.tipoRecuperacion = null;
            this.paso = 2;
            await this.reintentarRecuperacion();
        },

        async reintentarRecuperacion() {
            if (destruido || !this.recuperacion || this.recuperando) return;
            this.cancelarRecuperacion();
            const requestId = secuenciaRecuperacion;
            const contexto = this.recuperacion;
            const vigente = () => !destruido && requestId === secuenciaRecuperacion
                && this.recuperacion === contexto && this.fechaSeleccionada === contexto.fecha
                && this.inicioSeleccionado === contexto.inicio;
            controladorRecuperacion = typeof AbortController === 'undefined' ? null : new AbortController();
            const signal = controladorRecuperacion?.signal;
            contexto.estado = 'loading';
            this.mensajeConfirmacion = 'Estamos actualizando la disponibilidad. Esperá un momento.';
            try {
                if (contexto.fase === 'profesionales') {
                    this.estadoProfesionales = 'loading';
                    const payload = await consultarJson(config.urlProfesionalesDisponibles, { inicio_at: contexto.inicio }, signal, vigente);
                    if (!vigente()) return;
                    const opciones = leerProfesionales(payload, contexto.inicio);
                    this.profesionales = opciones;
                    this.inicioProfesionales = contexto.inicio;
                    this.estadoProfesionales = opciones.length ? 'success' : 'empty';
                    if (opciones.length) {
                        this.recuperacion = null;
                        this.tipoRecuperacion = 'R1';
                        this.mensajeConfirmacion = 'La profesional que habías seleccionado ya no está disponible para este horario.';
                        this.enfocarPaso('mensajeConfirmacion');
                        return;
                    }
                    contexto.fase = 'disponibilidad';
                }
                this.paso = 1;
                this.ventana = { ...contexto.ventana };
                this.diasDisponibles = [];
                this.ventanaConsultada = null;
                this.estadoDisponibilidad = 'loading';
                const payload = await consultarJson(config.urlDisponibilidad, contexto.ventana, signal, vigente);
                if (!vigente()) return;
                const dias = leerDias(payload, contexto.ventana);
                const fecha = dias.find((dia) => dia.fecha === contexto.fecha);
                // Dos proyecciones que cambiaron entre lecturas no prueban que el inicio se perdió.
                if (fecha?.horarios.some((horario) => horario.inicio_at === contexto.inicio)) {
                    contexto.fase = 'profesionales';
                    throw new Error('La disponibilidad cambió entre consultas.');
                }
                this.diasDisponibles = dias;
                this.ventanaConsultada = { ...contexto.ventana };
                this.estadoDisponibilidad = dias.length ? 'success' : 'empty';
                this.invalidarInicio();
                this.fechaSeleccionada = fecha ? contexto.fecha : null;
                this.recuperacion = null;
                this.tipoRecuperacion = fecha ? 'R2' : 'R3';
                this.mensajeConfirmacion = fecha
                    ? 'El horario que habías seleccionado ya no está disponible.'
                    : 'La fecha que habías seleccionado ya no tiene horarios disponibles.';
                this.enfocarPaso('mensajeConfirmacion');
            } catch (error) {
                if (!vigente() || error?.name === 'AbortError') return;
                contexto.estado = 'error';
                this.paso = contexto.fase === 'profesionales' ? 2 : 1;
                if (contexto.fase === 'profesionales') this.estadoProfesionales = 'error';
                else this.estadoDisponibilidad = 'error';
                this.mensajeConfirmacion = 'No se registró el turno. No pudimos actualizar la disponibilidad. Reintentá la consulta para continuar.';
                this.enfocarPaso('mensajeConfirmacion');
            } finally {
                if (requestId === secuenciaRecuperacion) controladorRecuperacion = null;
            }
        },

        construirConfirmacion(archivos) {
            const datos = new FormData();
            datos.append('clave_idempotencia', this.claveIdempotencia);
            datos.append('motivo_solicitud', this.motivo);
            datos.append('kinesiologa_id', this.kinesiologaId);
            datos.append('inicio_at', this.inicioSeleccionado);
            archivos.forEach((entrada, indice) => {
                datos.append(`adjuntos[${indice}][archivo]`, entrada.archivo);
                datos.append(`adjuntos[${indice}][descripcion]`, entrada.descripcion);
            });
            return datos;
        },

        async confirmarTurno() {
            if (destruido || !this.puedeConfirmar) return;
            this.enviando = true;
            this.resultadoIncierto = false;
            this.mensajeConfirmacion = '';
            // Copiar los valores de la tentativa; el índice Laravel se resuelve luego por este ID local.
            const archivosEnviados = this.archivos.map(({ id, archivo, descripcion }) => ({ id, archivo, descripcion }));
            const seleccionEnviada = { fecha: this.fechaSeleccionada, inicio: this.inicioSeleccionado,
                kinesiologa: this.kinesiologaId, ventana: { ...this.ventana } };
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
                } else if (response.status === 409 && ['profesional_no_elegible', 'slot_no_disponible'].includes(payload?.code)) {
                    await this.iniciarRecuperacion(seleccionEnviada);
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
                if (!destruido) this.enviando = false;
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
                    paso = Math.min(paso, 3);
                    reconocido = true;
                } else if (/^adjuntos\.\d+\.(archivo|descripcion)$/.test(campo)) {
                    const enviada = archivosEnviados[Number(campo.split('.')[1])];
                    const entrada = this.archivos.find((actual) => actual.id === enviada?.id);
                    if (entrada) {
                        if (campo.endsWith('.descripcion')) entrada.errorDescripcionServidor = mensaje;
                        else entrada.errorServidor = mensaje;
                        paso = Math.min(paso, 3);
                        reconocido = true;
                    }
                } else if (campo === 'kinesiologa_id') {
                    this.invalidarProfesional();
                    this.erroresServidor[campo] = mensaje;
                    paso = Math.min(paso, 2);
                    reconocido = true;
                } else if (campo === 'inicio_at') {
                    this.invalidarInicio();
                    this.erroresServidor[campo] = mensaje;
                    paso = 1;
                    refrescar = true;
                    reconocido = true;
                }
            }
            if (!reconocido) {
                this.confirmacionBloqueada = true;
                this.mensajeConfirmacion = 'No pudimos validar esta solicitud. Volvé a Mis turnos para iniciar un nuevo flujo.';
            }
            this.paso = paso;
            this.enfocarPaso(...(paso === 3
                ? (this.erroresServidor.motivo_solicitud ? ['motivo'] : ['documentacion'])
                : (paso === 2 ? ['errorProfesional'] : ['errorInicio'])));
            return refrescar && paso === 1;
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
                text: `Primera consulta con ${this.profesionalSeleccionada.nombre}. ${this.fechaLegible(this.inicioSeleccionado.slice(0, 10))}, ${this.horaLegible(this.inicioSeleccionado)} – ${this.horaLegible(this.profesionalSeleccionada.fin_at)}. Estado: Pendiente.`,
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
            if (this.bloqueado && this.recuperacion?.estado !== 'error') return;
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
