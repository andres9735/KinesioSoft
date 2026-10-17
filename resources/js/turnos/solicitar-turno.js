export default function solicitarTurno(config) {
    let siguienteArchivoId = 0;
    let avisoActivo = false;
    let antesDeSalir = null;

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
        ventana: null,

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
                this.kinesiologaId = elegida;
                this.slotSeleccionado = null;
                this.slotsDisponibles = [];
                this.ventana = null;
            }
            this.errorProfesional = '';
        },

        siguiente() {
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
            } else {
                return;
            }
            this.enfocarPaso();
        },

        anterior() {
            this.paso = Math.max(1, this.paso - 1);
            this.enfocarPaso();
        },

        enfocarPaso() {
            this.$nextTick(() => this.$refs[`tituloPaso${this.paso}`]?.focus());
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
