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

        get bloqueado() {
            return this.enviando || this.consolidado || this.redireccionando;
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
            this.cancelarConsultaProfesionales();
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
            return this.archivos.filter((entrada) => entrada.errorDescripcionServidor
                || typeof entrada.descripcion !== 'string' || !entrada.descripcion.trim()
                || [...entrada.descripcion.trim()].length > 500);
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
                error = 'Elegí un archivo con formato permitido.';
            } else if (archivo.size > this.limites.maxBytesPorArchivo) {
                error = `El archivo supera ${this.tamanioLegible(this.limites.maxBytesPorArchivo)}.`;
            }
            const id = ++siguienteArchivoId;
            if (error) {
                this.erroresSeleccionArchivos.push({ id, nombre: archivo.name, error });
                return null;
            }
            return { id, archivo, descripcion: '', error: '' };
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

        seleccionarFecha(fecha) {
            if (this.bloqueado || this.estadoDisponibilidad !== 'success'
                || !this.diasDisponibles.some((dia) => dia.fecha === fecha)) return;
            if (this.fechaSeleccionada !== fecha) {
                this.invalidarInicio();
                this.fechaSeleccionada = fecha;
            }
        },

        seleccionarInicio(inicio) {
            if (this.bloqueado || this.estadoDisponibilidad !== 'success'
                || !this.horariosDisponibles.some((horario) => horario.inicio_at === inicio)) return;
            if (this.inicioSeleccionado !== inicio) {
                this.limpiarProfesional();
                this.inicioSeleccionado = inicio;
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
            const opciones = { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' };
            if (controladorConsulta) opciones.signal = controladorConsulta.signal;
            const url = new URL(config.urlDisponibilidad);
            url.search = new URLSearchParams({ desde: ventana.desde, hasta: ventana.hasta });
            this.estadoDisponibilidad = 'loading';
            try {
                const response = await fetchImpl(url.toString(), opciones);
                if (!vigente()) return;
                if (response.status !== 200) {
                    this.tipoErrorDisponibilidad = response.status === 422 ? 'validacion' : 'tecnico';
                    throw new Error('No se pudo consultar disponibilidad.');
                }
                const payload = await response.json();
                if (!vigente()) return;
                this.diasDisponibles = leerDias(payload, ventana);
                this.ventanaConsultada = ventana;
                this.estadoDisponibilidad = this.diasDisponibles.length ? 'success' : 'empty';
                if (!this.diasDisponibles.some((dia) => dia.fecha === this.fechaSeleccionada)) this.fechaSeleccionada = null;
            } catch (error) {
                if (!vigente() || error?.name === 'AbortError') return;
                this.estadoDisponibilidad = 'error';
                this.tipoErrorDisponibilidad ??= 'tecnico';
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
            const opciones = { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' };
            if (controladorProfesionales) opciones.signal = controladorProfesionales.signal;
            const url = new URL(config.urlProfesionalesDisponibles);
            url.search = new URLSearchParams({ inicio_at: inicio });
            this.estadoProfesionales = 'loading';
            try {
                const response = await fetchImpl(url.toString(), opciones);
                if (!vigente()) return;
                if (response.status !== 200) {
                    this.tipoErrorProfesionales = response.status === 422 ? 'validacion' : 'tecnico';
                    throw new Error('No se pudo consultar profesionales.');
                }
                const payload = await response.json();
                if (!vigente()) return;
                this.profesionales = leerProfesionales(payload, inicio);
                this.inicioProfesionales = inicio;
                this.estadoProfesionales = this.profesionales.length ? 'success' : 'empty';
            } catch (error) {
                if (!vigente() || error?.name === 'AbortError') return;
                this.estadoProfesionales = 'error';
                this.tipoErrorProfesionales ??= 'tecnico';
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

        construirConfirmacion(archivos) {
            const datos = new FormData();
            datos.append('clave_idempotencia', this.claveIdempotencia);
            datos.append('motivo_solicitud', this.motivo);
            datos.append('kinesiologa_id', this.kinesiologaId);
            datos.append('inicio_at', this.inicioSeleccionado);
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
                    this.invalidarInicio();
                    this.paso = 1;
                    this.mensajeConfirmacion = 'Ese horario acaba de dejar de estar disponible. Elegí otro horario.';
                    this.enfocarPaso('mensajeConfirmacion');
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
