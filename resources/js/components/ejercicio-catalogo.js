export default function ejercicioCatalogo(config = {}) {
    let contadorNuevos = 0;

    const normalizarRecurso = (recurso = {}) => {
        const id = recurso.id === null || recurso.id === '' || recurso.id === undefined
            ? null
            : Number(recurso.id);
        const key = id
            ? `persisted-${id}`
            : `new-${contadorNuevos++}`;

        return {
            key,
            id,
            tipo: recurso.tipo || 'IMAGEN',
            origen: recurso.origen || 'LOCAL',
            origenOriginal: recurso.origen_original || recurso.origen || null,
            titulo: recurso.titulo || '',
            orden: Number.isFinite(Number(recurso.orden)) ? Number(recurso.orden) : 0,
            activo: recurso.activo === true || recurso.activo === 1 || recurso.activo === '1',
            urlExterna: recurso.url_externa || '',
            tieneArchivoActual: recurso.tiene_archivo_actual === true,
        };
    };

    return {
        recursos: Array.isArray(config.recursos)
            ? config.recursos.map(normalizarRecurso)
            : [],

        init() {
            this.actualizarOrdenes();
        },

        agregarRecurso() {
            this.recursos.push(normalizarRecurso({
                tipo: 'IMAGEN',
                origen: 'LOCAL',
                titulo: '',
                orden: this.recursos.length,
                activo: true,
                url_externa: '',
            }));
            this.actualizarOrdenes();
        },

        cambiarOrigen(recurso) {
            if (recurso.origen === 'LOCAL') {
                recurso.urlExterna = '';
            }
        },

        alternarActivo(recurso) {
            recurso.activo = !recurso.activo;
        },

        moverArriba(index) {
            if (index <= 0) {
                return;
            }

            [this.recursos[index - 1], this.recursos[index]] = [
                this.recursos[index],
                this.recursos[index - 1],
            ];
            this.actualizarOrdenes();
        },

        moverAbajo(index) {
            if (index >= this.recursos.length - 1) {
                return;
            }

            [this.recursos[index], this.recursos[index + 1]] = [
                this.recursos[index + 1],
                this.recursos[index],
            ];
            this.actualizarOrdenes();
        },

        quitarRecurso(index) {
            const recurso = this.recursos[index];

            if (!recurso || recurso.id) {
                return;
            }

            this.recursos.splice(index, 1);
            this.actualizarOrdenes();
        },

        actualizarOrdenes() {
            this.recursos.forEach((recurso, index) => {
                recurso.orden = index;
            });
        },

        necesitaArchivoNuevo(recurso) {
            return recurso.origen === 'LOCAL'
                && (!recurso.id || recurso.origenOriginal === 'EXTERNO');
        },
    };
}
