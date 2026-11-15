import assert from 'node:assert/strict';
import test from 'node:test';
import agendaAtencion from '../../resources/js/agenda/agenda-atencion.js';

const url = 'https://consultorio.example.test/turnos/agenda-atencion/turnos/7?desde=2026-11-01&hasta=2026-11-30&estado=PENDIENTE&page=2';
const respuesta = (html = '<dl><dt>Estado</dt><dd>Pendiente</dd></dl>', status = 200, marca = 'administrativo') => new Response(html, {
    status, headers: { 'Content-Type': 'text/html', 'X-Agenda-Detalle': marca },
});

function preparar({ fetchImpl, obtenerSwal, evento = {} } = {}) {
    const peticiones = [], actualizaciones = [], posiciones = [], focos = [], dialogos = [];
    const swal = {
        fire: (opciones) => { dialogos.push(opciones); return Promise.resolve({}); },
        update: (opciones) => actualizaciones.push(opciones),
    };
    const componente = agendaAtencion({
        obtenerSwal: obtenerSwal ?? (() => swal),
        fetchImpl: (...args) => { peticiones.push(args); return fetchImpl ? fetchImpl(...args) : Promise.resolve(respuesta()); },
        obtenerPosicion: () => ({ left: 12, top: 920 }),
        restaurarPosicion: (posicion) => posiciones.push(posicion),
    });
    const click = {
        button: 0, defaultPrevented: false,
        currentTarget: { href: url, focus: (opciones) => focos.push(opciones) },
        preventDefault() { this.defaultPrevented = true; },
        ...evento,
    };
    return { componente, click, peticiones, actualizaciones, posiciones, focos, dialogos };
}

test('abre con GET autorizado conservando periodo, filtros y pagina, sin navegar', async () => {
    const x = preparar();
    await x.componente.abrirDetalle(x.click);
    assert.equal(x.click.defaultPrevented, true);
    assert.equal(x.peticiones.length, 1);
    const [destino, opciones] = x.peticiones[0];
    assert.equal(destino, url);
    assert.equal(opciones.method, 'GET');
    assert.equal(opciones.credentials, 'same-origin');
    assert.equal(opciones.cache, 'no-store');
    assert.equal(opciones.redirect, 'error');
    assert.equal(opciones.headers['X-Agenda-Detalle'], 'modal');
    assert.match(x.actualizaciones[0].html, /Pendiente/);
    assert.equal(x.componente.abierto, true);
});

for (const cierre of ['Escape', 'boton Cerrar']) {
    test(`${cierre}: cancela la carga y restaura foco y desplazamiento`, async () => {
        const x = preparar();
        await x.componente.abrirDetalle(x.click);
        const modal = x.dialogos[0];
        assert.equal(modal.title, 'Detalle administrativo del turno');
        assert.equal(modal.allowEscapeKey, true);
        assert.equal(modal.showCloseButton, true);
        assert.equal(modal.confirmButtonText, 'Cerrar');
        assert.equal(modal.closeButtonAriaLabel, 'Cerrar detalle del turno');
        assert.equal(modal.focusConfirm, true);
        assert.equal(modal.theme, 'light');
        assert.equal(modal.background, '#ffffff');
        assert.equal(modal.buttonsStyling, false);
        assert.equal(modal.customClass.popup, 'uc05-detalle-modal');
        modal.willClose();
        assert.equal(x.peticiones[0][1].signal.aborted, true);
        modal.didClose();
        assert.equal(x.componente.abierto, false);
        assert.deepEqual(x.focos, [{ preventScroll: true }]);
        assert.deepEqual(x.posiciones, [{ left: 12, top: 920 }]);
        assert.equal(x.click.currentTarget.href, url);
    });
}

for (const status of [401, 403, 404, 419, 500, 503]) {
    test(`rechazo ${status}: no inserta el cuerpo de error ni datos ajenos`, async () => {
        const x = preparar({ fetchImpl: async () => respuesta('NO_MOSTRAR_DATOS_AJENOS', status) });
        await x.componente.abrirDetalle(x.click);
        assert.match(x.actualizaciones[0].html, /role="alert"/);
        assert.doesNotMatch(x.actualizaciones[0].html, /NO_MOSTRAR/);
    });
}

test('no inserta login ni HTML que no este identificado como detalle autorizado', async () => {
    const x = preparar({ fetchImpl: async () => respuesta('<form>LOGIN</form>', 200, '') });
    await x.componente.abrirDetalle(x.click);
    assert.match(x.actualizaciones[0].html, /No pudimos cargar/);
    assert.doesNotMatch(x.actualizaciones[0].html, /LOGIN/);
});

test('fallo de red mantiene la agenda y permite cerrar', async () => {
    const x = preparar({ fetchImpl: async () => { throw new Error('no exponer'); } });
    await x.componente.abrirDetalle(x.click);
    assert.match(x.actualizaciones[0].html, /No pudimos cargar/);
    assert.doesNotMatch(x.actualizaciones[0].html, /no exponer/);
    x.dialogos[0].didClose();
    assert.deepEqual(x.posiciones, [{ left: 12, top: 920 }]);
});

test('cerrar antes de recibir datos descarta respuestas tardias y permite otra apertura', async () => {
    let responder;
    const pendiente = new Promise((resolve) => { responder = resolve; });
    const x = preparar({ fetchImpl: () => pendiente });
    const apertura = x.componente.abrirDetalle(x.click);
    x.dialogos[0].willClose();
    x.dialogos[0].didClose();
    responder(respuesta());
    await apertura;
    assert.deepEqual(x.actualizaciones, []);
    await x.componente.abrirDetalle({ ...x.click, defaultPrevented: false });
    assert.equal(x.dialogos.length, 2);
});

test('doble activacion no abre otro modal ni repite la consulta', async () => {
    const x = preparar();
    await x.componente.abrirDetalle(x.click);
    await x.componente.abrirDetalle({ ...x.click, defaultPrevented: false });
    assert.equal(x.dialogos.length, 1);
    assert.equal(x.peticiones.length, 1);
});

for (const modificador of ['ctrlKey', 'metaKey', 'shiftKey', 'altKey']) {
    test(`${modificador}: conserva la navegacion normal del enlace`, async () => {
        const x = preparar({ evento: { [modificador]: true } });
        await x.componente.abrirDetalle(x.click);
        assert.equal(x.click.defaultPrevented, false);
        assert.equal(x.peticiones.length, 0);
    });
}

test('sin biblioteca modal mantiene el enlace de detalle como alternativa', async () => {
    const x = preparar({ obtenerSwal: () => undefined });
    await x.componente.abrirDetalle(x.click);
    assert.equal(x.click.defaultPrevented, false);
    assert.equal(x.click.currentTarget.href, url);
    assert.equal(x.peticiones.length, 0);
});

test('limpiar fechas solo vacia el periodo y enfoca su inicio, sin consultar ni alterar contexto', () => {
    const x = preparar();
    const campos = {
        desde: { value: '2026-11-01', focus: () => x.focos.push('desde') },
        hasta: { value: '2026-11-30' },
        fecha: { value: '2026-11-05' },
        paciente: { value: 'Paciente de muestra' },
        estado: { value: 'PENDIENTE' },
        tipo: { value: 'SEGUIMIENTO' },
        hora_desde: { value: '08:00' },
        hora_hasta: { value: '18:00' },
        page: { value: '2' },
    };
    const antes = Object.fromEntries(Object.entries(campos).map(([nombre, campo]) => [nombre, campo.value]));
    x.componente.limpiarFechas({ currentTarget: { form: { elements: { namedItem: (nombre) => campos[nombre] } } } });
    assert.equal(campos.desde.value, '');
    assert.equal(campos.hasta.value, '');
    for (const nombre of ['fecha', 'paciente', 'estado', 'tipo', 'hora_desde', 'hora_hasta', 'page']) {
        assert.equal(campos[nombre].value, antes[nombre]);
    }
    assert.deepEqual(x.focos, ['desde']);
    assert.deepEqual(x.peticiones, []);
    assert.deepEqual(x.actualizaciones, []);
    assert.deepEqual(x.posiciones, []);
});
