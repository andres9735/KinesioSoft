export default function agendaAtencion({
    fetchImpl = (...args) => globalThis.fetch(...args),
    obtenerSwal = () => globalThis.Swal,
    obtenerPosicion = () => ({ left: globalThis.scrollX, top: globalThis.scrollY }),
    restaurarPosicion = (posicion) => globalThis.scrollTo({ ...posicion, behavior: 'instant' }),
} = {}) {
    return {
        abierto: false,

        limpiarFechas(evento) {
            const campos = evento.currentTarget.form.elements;
            campos.namedItem('desde').value = '';
            campos.namedItem('hasta').value = '';
            campos.namedItem('desde').focus();
        },

        async abrirDetalle(evento) {
            // Conservar enlaces normales, pestañas nuevas y la alternativa sin JavaScript.
            if (evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
            const swal = obtenerSwal();
            if (!swal || typeof swal.fire !== 'function' || typeof swal.update !== 'function') return;
            evento.preventDefault();
            if (this.abierto) return;

            const enlace = evento.currentTarget;
            const posicion = obtenerPosicion();
            const controlador = new AbortController();
            this.abierto = true;
            swal.fire({
                title: 'Detalle administrativo del turno',
                html: '<p role="status">Consultando el detalle…</p>',
                width: '42rem',
                heightAuto: false,
                theme: 'light',
                background: '#ffffff',
                color: '#111827',
                backdrop: 'rgba(15, 23, 42, 0.22)',
                buttonsStyling: false,
                showCloseButton: true,
                closeButtonAriaLabel: 'Cerrar detalle del turno',
                confirmButtonText: 'Cerrar',
                focusConfirm: true,
                allowEscapeKey: true,
                returnFocus: false,
                customClass: { container: 'uc05-detalle-overlay', popup: 'uc05-detalle-modal', htmlContainer: 'uc05-detalle-body', confirmButton: 'uc05-modal-cerrar', closeButton: 'uc05-modal-x' },
                willClose: () => controlador.abort(),
                didClose: () => {
                    controlador.abort();
                    this.abierto = false;
                    enlace.focus({ preventScroll: true });
                    restaurarPosicion(posicion);
                },
            });

            try {
                const respuesta = await fetchImpl(enlace.href, {
                    method: 'GET', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                    signal: controlador.signal,
                    headers: { Accept: 'text/html', 'X-Agenda-Detalle': 'modal', 'X-Requested-With': 'XMLHttpRequest' },
                });
                // No insertar pantallas de login, rechazos ni errores en lugar del detalle autorizado.
                if (!respuesta.ok || respuesta.redirected || respuesta.headers.get('X-Agenda-Detalle') !== 'administrativo') throw new Error();
                const html = await respuesta.text();
                if (!controlador.signal.aborted) swal.update({ html });
            } catch {
                if (!controlador.signal.aborted) {
                    swal.update({ html: '<p role="alert">No pudimos cargar el detalle. Cerrá esta ventana e intentá nuevamente desde la agenda.</p>' });
                }
            }
        },
    };
}
