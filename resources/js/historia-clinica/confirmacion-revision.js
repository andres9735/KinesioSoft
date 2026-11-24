// Presentación de la respuesta HTML existente. No compone ni confirma datos clínicos.
const incierto = () => ({ estado: 'incierto' });
const tiposError = {
    autorizacion: [403], version: [409], conflicto: [409], minimo: [422],
    confirmacion: [422], guardado: [500, 503],
};
const identificador = valor => Number.isSafeInteger(valor) && valor > 0;

export function leerRespuestaConfirmacion(html, respuesta, config, parsear = texto => new DOMParser().parseFromString(texto, 'text/html')) {
    if (respuesta.redirected || !respuesta.headers.get('Content-Type')?.includes('text/html')) return incierto();
    try {
        const nodos = parsear(html).querySelectorAll('[data-resultado-revision]');
        if (nodos.length !== 1) return incierto();
        const datos = JSON.parse(nodos[0].getAttribute('data-resultado-revision'));
        if (datos.estado === 'error' && tiposError[datos.tipo]?.includes(respuesta.status)) return datos;
        if (!respuesta.ok || respuesta.status !== 200 || datos.estado !== 'incorporada'
            || datos.turno !== config.turno || datos.declaracion !== config.declaracion
            || !identificador(datos.version) || !identificador(datos.declaracion)
            || datos.estadoDeclaracion !== 'Revisada' || typeof datos.yaProcesada !== 'boolean'
            || !['historia', 'autor', 'especialidad', 'fecha'].every(c => typeof datos[c] === 'string' && datos[c].trim())
            || datos.expedienteUrl !== config.expedienteUrl || datos.turnoUrl !== config.turnoUrl) return incierto();
        return datos;
    } catch {
        return incierto();
    }
}

export function describirResultado(datos, config) {
    if (datos.estado === 'incorporada') return {
        icon: 'success', title: 'Revisión confirmada e incorporada',
        mensaje: datos.yaProcesada
            ? 'Esta confirmación ya fue procesada. Se recuperó la incorporación registrada sin generar otra versión, dictámenes ni auditorías. El expediente puede contener versiones posteriores.'
            : 'Los datos admitidos fueron registrados en una versión clínica profesional del expediente.',
        detalles: [['Historia clínica', datos.historia], ['Versión clínica registrada', String(datos.version)],
            ['Declaración', '#' + datos.declaracion], ['Estado de la declaración', datos.estadoDeclaracion],
            ['Profesional responsable', datos.autor], ['Especialidad', datos.especialidad], ['Fecha y hora', datos.fecha]],
        nota: 'La incorporación no inicia automáticamente la atención ni modifica por sí misma el estado del turno.',
        principal: ['Consultar expediente actualizado', config.expedienteUrl],
        secundaria: config.turnoUrl ? ['Volver al turno', config.turnoUrl] : null,
    };
    if (datos.estado === 'error' && ['version', 'conflicto'].includes(datos.tipo)) return {
        icon: 'warning', title: 'La propuesta ya no puede confirmarse',
        mensaje: 'El contexto cambió o la solicitud es incompatible. Recuperá la revisión y generá una nueva vista previa antes de confirmar.',
        principal: ['Volver a la revisión', config.revisionUrl],
    };
    if (datos.estado === 'error' && datos.tipo === 'autorizacion') return {
        icon: 'error', title: 'Sin autorización para confirmar',
        mensaje: 'Se requiere la habilitación, los permisos clínicos y la asignación vigente al turno. No se registró una nueva incorporación mediante esta solicitud.',
        principal: ['Volver al expediente', config.expedienteUrl],
    };
    if (datos.estado === 'error' && ['minimo', 'confirmacion'].includes(datos.tipo)) return {
        icon: 'warning', title: datos.tipo === 'minimo' ? 'Información clínica insuficiente o inválida' : 'Confirmación explícita pendiente o formulario inválido',
        mensaje: 'No se registró una nueva incorporación mediante esta solicitud. Revisá la propuesta y generá una nueva vista previa. La conformidad profesional debe ser explícita.',
        principal: ['Volver a la revisión', config.revisionUrl],
    };
    // El contrato guardado no acredita un fracaso definitivo, aun con HTTP 500/503.
    return {
        icon: 'info', title: 'No se pudo determinar el resultado',
        mensaje: 'La respuesta no permitió verificar el resultado de la incorporación. Consultá el expediente antes de intentar una nueva confirmación. No se reenviará esta solicitud automáticamente.',
        principal: ['Consultar expediente', config.expedienteUrl],
        secundaria: ['Volver a la revisión', config.revisionUrl],
    };
}

export function contenidoResultado(descripcion, documento = globalThis.document) {
    const contenido = documento.createElement('div');
    contenido.className = 'uc14-confirmacion-contenido';
    const agregarTexto = (elemento, texto, clase) => {
        const nodo = documento.createElement(elemento);
        nodo.textContent = texto;
        if (clase) nodo.className = clase;
        contenido.append(nodo);
    };
    agregarTexto('p', descripcion.mensaje);
    if (descripcion.detalles) {
        const lista = documento.createElement('dl');
        for (const [etiqueta, valor] of descripcion.detalles) {
            const fila = documento.createElement('div');
            const termino = documento.createElement('dt');
            const dato = documento.createElement('dd');
            termino.textContent = etiqueta;
            dato.textContent = valor;
            fila.append(termino, dato);
            lista.append(fila);
        }
        contenido.append(lista);
    }
    if (descripcion.nota) agregarTexto('p', descripcion.nota, 'uc14-confirmacion-nota');
    return contenido;
}

export default function confirmacionRevision(config, {
    fetchImpl = (...args) => globalThis.fetch(...args),
    obtenerSwal = () => globalThis.Swal,
    leer = leerRespuestaConfirmacion,
    crearContenido = contenidoResultado,
    formularioDatos = formulario => new FormData(formulario),
    navegar = destino => globalThis.location.assign(destino),
    enfocarRecuperacion = () => globalThis.document.querySelector('[data-recuperar-revision]')?.focus(),
} = {}) {
    return {
        conforme: false, procesando: false, bloqueada: false, consumada: false, aviso: '',

        init() {
            if (!config.respuestaInicial) return;
            const datos = leer(this.$el.outerHTML, { ok: config.status === 200, status: config.status,
                headers: { get: () => 'text/html' } }, config);
            this.bloqueada = true;
            this.consumada = datos.estado === 'incorporada';
            this.mostrar(datos);
        },

        async enviar(evento, propuestaEditada = false) {
            evento.preventDefault();
            if (this.procesando || this.bloqueada || propuestaEditada) return;
            const formulario = evento.currentTarget;
            if (!this.conforme || !formulario.checkValidity()) {
                formulario.reportValidity();
                return;
            }
            // Capturar los tres campos existentes antes de deshabilitar el fieldset.
            const cuerpo = formularioDatos(formulario);
            this.procesando = true;
            this.bloqueada = true;
            let datos;
            try {
                if (formulario.action !== config.confirmarUrl) throw new Error();
                const respuesta = await fetchImpl(config.confirmarUrl, {
                    method: 'POST', body: cuerpo, credentials: 'same-origin', cache: 'no-store',
                    redirect: 'error', headers: { Accept: 'text/html' },
                });
                datos = leer(await respuesta.text(), respuesta, config);
            } catch {
                datos = incierto();
            } finally {
                this.procesando = false;
            }
            this.consumada = datos.estado === 'incorporada';
            if (this.consumada) this.$dispatch?.('revision-incorporada');
            await this.mostrar(datos);
        },

        async mostrar(datos) {
            const descripcion = describirResultado(datos, config);
            this.aviso = descripcion.title + '. ' + descripcion.mensaje;
            try {
                const swal = obtenerSwal();
                if (!swal?.fire) return;
                const eleccion = await swal.fire({
                    icon: descripcion.icon, title: descripcion.title, html: crearContenido(descripcion),
                    width: '44rem', heightAuto: false, theme: 'light', background: '#ffffff', color: '#111827',
                    buttonsStyling: false, allowOutsideClick: false, showCloseButton: true,
                    closeButtonAriaLabel: 'Cerrar resultado de la revisión', allowEscapeKey: true,
                    confirmButtonText: descripcion.principal[0], showDenyButton: !!descripcion.secundaria,
                    denyButtonText: descripcion.secundaria?.[0], focusConfirm: true, returnFocus: false,
                    customClass: { popup: 'uc14-confirmacion-modal', confirmButton: 'uc14-confirmacion-principal',
                        denyButton: 'uc14-confirmacion-secundaria' },
                });
                if (eleccion.isDenied && descripcion.secundaria) navegar(descripcion.secundaria[1]);
                else if (eleccion.isConfirmed || this.consumada) navegar(descripcion.principal[1]);
                else enfocarRecuperacion();
            } catch {
                // La indisponibilidad del modal no modifica el resultado ni reenvía el POST.
                enfocarRecuperacion();
            }
        },
    };
}
