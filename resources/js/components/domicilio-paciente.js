const MENSAJE_ERROR_PROVINCIAS =
    'No pudimos cargar las provincias. Reintentá.';
const MENSAJE_ERROR_LOCALIDADES =
    'No pudimos cargar las ciudades. Reintentá.';
const MENSAJE_ERROR_CALLES =
    'No pudimos buscar sugerencias. Podés continuar con la calle ingresada.';

function valorInicial(valor) {
    return valor === null || valor === undefined
        ? ''
        : String(valor);
}

function idNumerico(valor) {
    const id = valorInicial(valor).trim();

    return /^\d+$/.test(id) ? id : null;
}

function urlConId(template, id) {
    return template.replace('__ID__', encodeURIComponent(id));
}

function esAbortError(error) {
    return error instanceof DOMException && error.name === 'AbortError';
}

export default function domicilioPaciente(config = {}) {
    return {
        urlProvincias: config.urlProvincias || '',
        urlLocalidadesTemplate: config.urlLocalidadesTemplate || '',
        urlCallesTemplate: config.urlCallesTemplate || '',

        provincias: [],
        localidades: [],
        sugerenciasCalles: [],

        provinciaId: valorInicial(config.provinciaIdInicial),
        localidadId: valorInicial(config.localidadIdInicial),

        calle: valorInicial(config.calleInicial),
        alturaDomicilio: valorInicial(config.alturaDomicilioInicial),
        pisoDepartamento: valorInicial(config.pisoDepartamentoInicial),
        referenciaDomicilio: valorInicial(config.referenciaDomicilioInicial),

        cargandoProvincias: false,
        cargandoLocalidades: false,
        buscandoCalles: false,

        errorProvincias: '',
        errorLocalidades: '',
        errorCalles: '',

        mostrarSugerencias: false,
        indiceSugerenciaActiva: -1,
        busquedaSinResultados: false,

        cacheLocalidades: new Map(),
        temporizadorBusqueda: null,
        controladorLocalidades: null,
        controladorCalles: null,

        async init() {
            await this.cargarProvincias();

            if (this.provinciaSeleccionadaValida()) {
                await this.cargarLocalidades();
            }
        },

        destroy() {
            this.cancelarCargaLocalidades();
            this.cancelarBusquedaCalles();
            this.cancelarDebounce();
        },

        async cargarProvincias() {
            if (this.cargandoProvincias || this.provincias.length > 0) {
                return;
            }

            this.cargandoProvincias = true;
            this.errorProvincias = '';

            try {
                const respuesta = await fetch(this.urlProvincias, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!respuesta.ok) {
                    throw new Error('No se pudieron cargar las provincias.');
                }

                const datos = await respuesta.json();

                if (!Array.isArray(datos)) {
                    throw new TypeError('La respuesta de provincias no es una lista.');
                }

                this.provincias = datos;
            } catch (error) {
                console.error(error);
                this.provincias = [];
                this.errorProvincias = MENSAJE_ERROR_PROVINCIAS;
            } finally {
                this.cargandoProvincias = false;
            }
        },

        async reintentarProvincias() {
            await this.cargarProvincias();

            if (this.provinciaSeleccionadaValida()) {
                await this.cargarLocalidades();
            }
        },

        async onProvinciaChange() {
            this.cancelarCargaLocalidades();
            this.cancelarBusquedaCalles();
            this.cancelarDebounce();

            this.localidadId = '';
            this.localidades = [];
            this.calle = '';
            this.limpiarSugerenciasCalles();
            this.errorLocalidades = '';
            this.errorCalles = '';

            if (this.provinciaSeleccionadaValida()) {
                await this.cargarLocalidades();
            }
        },

        async cargarLocalidades() {
            const provinciaSolicitada = idNumerico(this.provinciaId);

            if (!provinciaSolicitada) {
                return;
            }

            if (this.cacheLocalidades.has(provinciaSolicitada)) {
                if (provinciaSolicitada === idNumerico(this.provinciaId)) {
                    this.localidades = this.cacheLocalidades.get(
                        provinciaSolicitada
                    );
                }

                return;
            }

            this.cancelarCargaLocalidades();

            const controlador = new AbortController();
            this.controladorLocalidades = controlador;
            this.cargandoLocalidades = true;
            this.errorLocalidades = '';

            try {
                const respuesta = await fetch(
                    urlConId(
                        this.urlLocalidadesTemplate,
                        provinciaSolicitada
                    ),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controlador.signal,
                    }
                );

                if (!respuesta.ok) {
                    throw new Error('No se pudieron cargar las ciudades.');
                }

                const datos = await respuesta.json();

                if (!Array.isArray(datos)) {
                    throw new TypeError('La respuesta de ciudades no es una lista.');
                }

                const localidades = this.prepararEtiquetasLocalidades(datos);
                this.cacheLocalidades.set(provinciaSolicitada, localidades);

                if (provinciaSolicitada === idNumerico(this.provinciaId)) {
                    this.localidades = localidades;
                }
            } catch (error) {
                if (esAbortError(error)) {
                    return;
                }

                console.error(error);

                if (provinciaSolicitada === idNumerico(this.provinciaId)) {
                    this.localidades = [];
                    this.errorLocalidades = MENSAJE_ERROR_LOCALIDADES;
                }
            } finally {
                if (this.controladorLocalidades === controlador) {
                    this.controladorLocalidades = null;
                    this.cargandoLocalidades = false;
                }
            }
        },

        prepararEtiquetasLocalidades(localidades) {
            const repeticiones = new Map();

            localidades.forEach((localidad) => {
                const clave = JSON.stringify([
                    localidad.nombre,
                    localidad.departamento_nombre,
                ]);

                repeticiones.set(
                    clave,
                    (repeticiones.get(clave) || 0) + 1
                );
            });

            return localidades.map((localidad) => {
                const clave = JSON.stringify([
                    localidad.nombre,
                    localidad.departamento_nombre,
                ]);
                const etiquetaBase =
                    `${localidad.nombre} — ${localidad.departamento_nombre}`;

                return {
                    ...localidad,
                    codigo_censal: String(localidad.codigo_censal),
                    etiqueta: repeticiones.get(clave) > 1
                        ? `${etiquetaBase} — código censal ${String(localidad.codigo_censal)}`
                        : etiquetaBase,
                };
            });
        },

        async reintentarLocalidades() {
            await this.cargarLocalidades();
        },

        onLocalidadChange() {
            this.cancelarBusquedaCalles();
            this.cancelarDebounce();

            this.calle = '';
            this.limpiarSugerenciasCalles();
            this.errorCalles = '';
        },

        onCalleInput() {
            this.cancelarDebounce();
            this.cancelarBusquedaCalles();
            this.limpiarSugerenciasCalles();
            this.errorCalles = '';

            const termino = this.calle.trim();
            const localidad = idNumerico(this.localidadId);

            if (!localidad || termino.length < 2) {
                return;
            }

            this.temporizadorBusqueda = window.setTimeout(() => {
                this.temporizadorBusqueda = null;
                this.buscarCalles(localidad, termino);
            }, 250);
        },

        async buscarCalles(localidadSolicitada, terminoSolicitado) {
            if (
                localidadSolicitada !== idNumerico(this.localidadId)
                || terminoSolicitado !== this.calle.trim()
            ) {
                return;
            }

            const controlador = new AbortController();
            this.controladorCalles = controlador;
            this.buscandoCalles = true;

            try {
                const url = new URL(
                    urlConId(this.urlCallesTemplate, localidadSolicitada),
                    window.location.origin
                );
                url.searchParams.set('q', terminoSolicitado);

                const respuesta = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controlador.signal,
                });

                if (!respuesta.ok) {
                    throw new Error('No se pudieron buscar calles.');
                }

                const datos = await respuesta.json();

                if (!Array.isArray(datos)) {
                    throw new TypeError('La respuesta de calles no es una lista.');
                }

                if (
                    localidadSolicitada !== idNumerico(this.localidadId)
                    || terminoSolicitado !== this.calle.trim()
                ) {
                    return;
                }

                this.sugerenciasCalles = datos.filter(
                    (calle) => typeof calle?.nombre === 'string'
                );
                this.busquedaSinResultados =
                    this.sugerenciasCalles.length === 0;
                this.mostrarSugerencias =
                    this.sugerenciasCalles.length > 0;
                this.indiceSugerenciaActiva = -1;
            } catch (error) {
                if (esAbortError(error)) {
                    return;
                }

                console.error(error);

                if (
                    localidadSolicitada === idNumerico(this.localidadId)
                    && terminoSolicitado === this.calle.trim()
                ) {
                    this.limpiarSugerenciasCalles();
                    this.errorCalles = MENSAJE_ERROR_CALLES;
                }
            } finally {
                if (this.controladorCalles === controlador) {
                    this.controladorCalles = null;
                    this.buscandoCalles = false;
                }
            }
        },

        seleccionarSugerencia(sugerencia) {
            if (!sugerencia || typeof sugerencia.nombre !== 'string') {
                return;
            }

            this.cancelarDebounce();
            this.cancelarBusquedaCalles();
            this.calle = sugerencia.nombre;
            this.limpiarSugerenciasCalles();
            this.errorCalles = '';

            this.$nextTick(() => this.$refs.calle?.focus());
        },

        moverSugerencia(desplazamiento) {
            if (!this.mostrarSugerencias || this.sugerenciasCalles.length === 0) {
                return;
            }

            const total = this.sugerenciasCalles.length;

            if (this.indiceSugerenciaActiva < 0) {
                this.indiceSugerenciaActiva = desplazamiento > 0 ? 0 : total - 1;

                return;
            }

            this.indiceSugerenciaActiva =
                (this.indiceSugerenciaActiva + desplazamiento + total) % total;
        },

        seleccionarSugerenciaActiva() {
            if (
                !this.mostrarSugerencias
                || this.indiceSugerenciaActiva < 0
            ) {
                return false;
            }

            this.seleccionarSugerencia(
                this.sugerenciasCalles[this.indiceSugerenciaActiva]
            );

            return true;
        },

        abrirSugerencias() {
            if (this.sugerenciasCalles.length > 0) {
                this.mostrarSugerencias = true;
            }
        },

        cerrarSugerencias() {
            this.mostrarSugerencias = false;
            this.indiceSugerenciaActiva = -1;
        },

        limpiarSugerenciasCalles() {
            this.sugerenciasCalles = [];
            this.mostrarSugerencias = false;
            this.indiceSugerenciaActiva = -1;
            this.busquedaSinResultados = false;
        },

        cancelarDebounce() {
            if (this.temporizadorBusqueda !== null) {
                window.clearTimeout(this.temporizadorBusqueda);
                this.temporizadorBusqueda = null;
            }
        },

        cancelarCargaLocalidades() {
            if (this.controladorLocalidades) {
                this.controladorLocalidades.abort();
                this.controladorLocalidades = null;
            }

            this.cargandoLocalidades = false;
        },

        cancelarBusquedaCalles() {
            if (this.controladorCalles) {
                this.controladorCalles.abort();
                this.controladorCalles = null;
            }

            this.buscandoCalles = false;
        },

        provinciaSeleccionadaValida() {
            const provinciaId = idNumerico(this.provinciaId);

            return provinciaId !== null && this.provincias.some(
                (provincia) => String(provincia.id) === provinciaId
            );
        },

        localidadSeleccionadaValida() {
            const localidadId = idNumerico(this.localidadId);

            return localidadId !== null && this.localidades.some(
                (localidad) => String(localidad.id) === localidadId
            );
        },

        placeholderProvincia() {
            if (this.cargandoProvincias) {
                return 'Cargando provincias…';
            }

            if (this.errorProvincias) {
                return 'No se pudieron cargar las provincias';
            }

            if (this.provincias.length === 0) {
                return 'No hay provincias disponibles';
            }

            return 'Seleccione una provincia';
        },

        placeholderLocalidad() {
            if (!this.provinciaSeleccionadaValida()) {
                return 'Primero seleccioná una provincia';
            }

            if (this.cargandoLocalidades) {
                return 'Cargando ciudades…';
            }

            if (this.errorLocalidades) {
                return 'No se pudieron cargar las ciudades';
            }

            if (this.localidades.length === 0) {
                return 'No hay ciudades disponibles';
            }

            return 'Seleccione una ciudad';
        },

        idSugerenciaActiva() {
            return this.mostrarSugerencias
                && this.indiceSugerenciaActiva >= 0
                ? `calle-sugerencia-${this.indiceSugerenciaActiva}`
                : null;
        },
    };
}
