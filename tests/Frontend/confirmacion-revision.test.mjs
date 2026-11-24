import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import confirmacionRevision, { leerRespuestaConfirmacion, describirResultado, contenidoResultado } from '../../resources/js/historia-clinica/confirmacion-revision.js';

const config = { turno: 26, declaracion: 3, confirmarUrl: 'https://prueba.test/confirmar',
    revisionUrl: 'https://prueba.test/revision', expedienteUrl: 'https://prueba.test/expediente', turnoUrl: 'https://prueba.test/turno' };
const exito = { estado: 'incorporada', turno: 26, declaracion: 3, version: 2, estadoDeclaracion: 'Revisada',
    historia: 'HC-FICTICIA', autor: 'Profesional ficticia', especialidad: 'Especialidad ficticia', fecha: '24/11/2026',
    yaProcesada: false, expedienteUrl: config.expedienteUrl, turnoUrl: config.turnoUrl };
const respuesta = (status = 200) => ({ ok: status === 200, status, headers: { get: () => 'text/html; charset=UTF-8' }, text: async () => '<html></html>' });
const parser = datos => () => ({ querySelectorAll: () => [{ getAttribute: () => JSON.stringify(datos) }] });
const leer = (datos, status = 200, ajustes = {}) => leerRespuestaConfirmacion('', { ...respuesta(status), ...ajustes }, config, parser(datos));
function entorno({ datos = exito, status = 200, eleccion = {}, fetchImpl } = {}) {
    const peticiones = [], modales = [], destinos = [], eventos = [];
    let focos = 0, invalido = false, valido = true;
    const estado = confirmacionRevision(config, {
        fetchImpl: fetchImpl ?? (async (...args) => { peticiones.push(args); return respuesta(status); }),
        obtenerSwal: () => ({ fire: async opciones => { modales.push(opciones); return eleccion; } }),
        leer: () => datos, crearContenido: descripcion => descripcion,
        formularioDatos: () => { assert.equal(estado.procesando, false); return ['_token', 'confirmacion', 'confirmar']; },
        navegar: url => destinos.push(url), enfocarRecuperacion: () => focos++,
    });
    estado.$dispatch = nombre => eventos.push(nombre);
    const evento = { preventDefault() {}, currentTarget: { action: config.confirmarUrl,
        checkValidity: () => valido, reportValidity: () => { invalido = true; } } };
    return { estado, evento, peticiones, modales, destinos, eventos, focos: () => focos,
        invalidar: () => { valido = false; }, reportado: () => invalido };
}

test('DUX-07 inicia sin conformidad y no confirma durante init ni con formulario inválido', async () => {
    const e = entorno();
    e.estado.init();
    assert.equal(e.estado.conforme, false);
    await e.estado.enviar(e.evento);
    e.estado.conforme = true;
    e.invalidar();
    await e.estado.enviar(e.evento);
    assert.equal(e.reportado(), true);
    assert.equal(e.peticiones.length, 0);
    assert.equal(e.modales.length, 0);
});

test('DUX-07 no permite confirmar una propuesta editada', async () => {
    const e = entorno();
    e.estado.conforme = true;
    await e.estado.enviar(e.evento, true);
    assert.equal(e.peticiones.length, 0);
});

test('DUX-07 bloquea doble envío durante y después del POST y conserva su contrato', async () => {
    let resolver;
    const llamadas = [];
    const e = entorno({ fetchImpl: (...args) => { llamadas.push(args); return new Promise(r => { resolver = r; }); } });
    e.estado.conforme = true;
    const envio = e.estado.enviar(e.evento);
    assert.equal(e.estado.procesando, true);
    await e.estado.enviar(e.evento);
    assert.equal(e.modales.length, 0); // No hay un segundo modal previo de confirmación.
    assert.equal(llamadas.length, 1);
    assert.equal(llamadas[0][0], config.confirmarUrl);
    assert.deepEqual(llamadas[0][1].body, ['_token', 'confirmacion', 'confirmar']);
    assert.equal(llamadas[0][1].method, 'POST');
    assert.equal(llamadas[0][1].redirect, 'error');
    assert.equal(llamadas[0][1].credentials, 'same-origin');
    resolver(respuesta());
    await envio;
    await e.estado.enviar(e.evento);
    assert.equal(e.estado.procesando, false);
    assert.equal(e.estado.bloqueada, true);
    assert.equal(e.estado.consumada, true);
    assert.deepEqual(e.eventos, ['revision-incorporada']);
    assert.equal(llamadas.length, 1);
});

for (const [nombre, eleccion, destino] of [
    ['consultar', { isConfirmed: true }, config.expedienteUrl],
    ['volver al turno', { isDenied: true }, config.turnoUrl],
    ['cerrar', { isDismissed: true }, config.expedienteUrl],
]) test('DUX-07 éxito: ' + nombre + ' navega sin repetir la incorporación', async () => {
    const e = entorno({ eleccion });
    e.estado.conforme = true;
    await e.estado.enviar(e.evento);
    assert.deepEqual(e.destinos, [destino]);
    assert.equal(e.peticiones.length, 1);
    const modal = e.modales[0];
    assert.equal(modal.title, 'Revisión confirmada e incorporada');
    assert.equal(modal.icon, 'success');
    assert.equal(Object.hasOwn(modal, 'timer'), false);
    assert.equal(modal.allowOutsideClick, false);
    assert.equal(modal.showCloseButton, true);
    assert.equal(modal.focusConfirm, true);
    assert.equal(modal.returnFocus, false);
    assert.equal(modal.confirmButtonText, 'Consultar expediente actualizado');
    assert.deepEqual(modal.html.detalles.find(d => d[0] === 'Versión clínica registrada'), ['Versión clínica registrada', '2']);
    assert.match(modal.html.nota, /no inicia automáticamente la atención/);
});

test('DUX-07 reintento ya procesado informa la versión registrada sin estimar la vigente', () => {
    const descripcion = describirResultado({ ...exito, version: 1, yaProcesada: true }, config);
    assert.match(descripcion.mensaje, /Esta confirmación ya fue procesada/);
    assert.match(descripcion.mensaje, /versiones posteriores/);
    assert.equal(descripcion.detalles[1][1], '1');
});

for (const tipo of ['version', 'conflicto', 'autorizacion', 'minimo', 'confirmacion']) {
    test('DUX-07 rechazo ' + tipo + ' no anuncia éxito ni reintenta y restaura foco al cerrar', async () => {
        const e = entorno({ datos: { estado: 'error', tipo }, status: tipo === 'autorizacion' ? 403 : ['version', 'conflicto'].includes(tipo) ? 409 : 422 });
        e.estado.conforme = true;
        await e.estado.enviar(e.evento);
        await e.estado.enviar(e.evento);
        assert.equal(e.modales[0].icon, tipo === 'autorizacion' ? 'error' : 'warning');
        assert.notEqual(e.modales[0].title, 'Revisión confirmada e incorporada');
        assert.equal(e.modales[0].html.detalles, undefined);
        assert.equal(e.peticiones.length, 1);
        assert.equal(e.estado.consumada, false);
        assert.equal(e.focos(), 1);
        assert.deepEqual(e.destinos, []);
    });
}

test('DUX-07 red interrumpida es incierta, no registra detalles técnicos y no reenvía', async () => {
    let llamadas = 0;
    const e = entorno({ fetchImpl: async () => { llamadas++; throw new Error('SQL token secreto'); } });
    e.estado.conforme = true;
    await e.estado.enviar(e.evento);
    await e.estado.enviar(e.evento);
    assert.equal(e.modales[0].title, 'No se pudo determinar el resultado');
    assert.doesNotMatch(e.estado.aviso, /SQL|token secreto/);
    assert.equal(llamadas, 1);
    assert.equal(e.estado.consumada, false);
});

test('DUX-07 guardado 500/503 no acredita fracaso técnico definitivo', () => {
    for (const status of [500, 503]) {
        const datos = leer({ estado: 'error', tipo: 'guardado' }, status);
        const descripcion = describirResultado(datos, config);
        assert.equal(descripcion.icon, 'info');
        assert.equal(descripcion.title, 'No se pudo determinar el resultado');
        assert.doesNotMatch(descripcion.mensaje, /no se realizó|fracasó/);
    }
});

test('DUX-07 verifica los rechazos HTML reconocidos con su código HTTP exacto', () => {
    for (const [tipo, status] of [['autorizacion', 403], ['version', 409], ['conflicto', 409], ['minimo', 422], ['confirmacion', 422]]) {
        assert.deepEqual(leer({ estado: 'error', tipo }, status), { estado: 'error', tipo });
        assert.equal(leer({ estado: 'error', tipo }, 200).estado, 'incierto');
    }
});

test('DUX-07 una respuesta interrumpida después del envío sigue siendo incierta', async () => {
    let peticiones = 0;
    const e = entorno({ fetchImpl: async () => {
        peticiones++;
        return { ...respuesta(), text: async () => { throw new Error('Respuesta interrumpida'); } };
    } });
    e.estado.conforme = true;
    await e.estado.enviar(e.evento);
    assert.equal(peticiones, 1);
    assert.equal(e.modales[0].title, 'No se pudo determinar el resultado');
    assert.equal(e.estado.bloqueada, true);
});

test('DUX-07 mostrar una respuesta inicial del POST no vuelve a confirmar', async () => {
    let peticiones = 0;
    const modales = [];
    const estado = confirmacionRevision({ ...config, respuestaInicial: true, status: 200 }, {
        fetchImpl: async () => { peticiones++; }, leer: () => exito,
        obtenerSwal: () => ({ fire: async m => { modales.push(m); return {}; } }),
        crearContenido: d => d, navegar: () => {}, enfocarRecuperacion: () => {},
    });
    estado.$el = { outerHTML: 'Respuesta HTML ya registrada' };
    estado.init();
    await Promise.resolve();
    assert.equal(peticiones, 0);
    assert.equal(modales.length, 1);
    assert.equal(estado.bloqueada, true);
    assert.equal(estado.consumada, true);
    assert.equal(estado.conforme, false);
});

test('DUX-07 respuestas incompletas, redirigidas o de otro contexto nunca son éxito', () => {
    assert.deepEqual(leer(exito), exito);
    for (const cambios of [{ turno: 27 }, { declaracion: 4 }, { version: 0 }, { estadoDeclaracion: 'Pendiente' },
        { expedienteUrl: 'https://otro.test' }, { turnoUrl: 'javascript:alert(1)' }, { autor: '' }, { yaProcesada: null }]) {
        assert.equal(leer({ ...exito, ...cambios }).estado, 'incierto');
    }
    assert.equal(leer(exito, 500).estado, 'incierto');
    assert.equal(leer(exito, 200, { redirected: true }).estado, 'incierto');
    assert.equal(leer(exito, 200, { headers: { get: () => 'application/json' } }).estado, 'incierto');
    for (const nodos of [[], [{ getAttribute: () => '{inválido' }], [{}, {}]]) {
        assert.equal(leerRespuestaConfirmacion('', respuesta(), config, () => ({ querySelectorAll: () => nodos })).estado, 'incierto');
    }
    assert.equal(leer({ estado: 'error', tipo: 'desconocido' }, 503).estado, 'incierto');
});

test('DUX-07 falla el modal sin repetir el POST ni convertir un éxito en error', async () => {
    const e = entorno();
    e.estado.conforme = true;
    const sinModal = confirmacionRevision(config, {
        fetchImpl: async () => { e.peticiones.push('POST'); return respuesta(); },
        leer: () => exito, obtenerSwal: () => ({ fire: async () => { throw new Error(); } }),
        formularioDatos: () => ({}), crearContenido: x => x, enfocarRecuperacion: () => {},
    });
    sinModal.conforme = true;
    await sinModal.enviar(e.evento);
    assert.equal(sinModal.consumada, true);
    assert.match(sinModal.aviso, /^Revisión confirmada e incorporada/);
    assert.equal(e.peticiones.length, 1);
});

test('DUX-07 contenido usa nodos de texto y no interpreta HTML en identificadores', () => {
    const documento = { createElement: tag => ({ tag, children: [], append(...hijos) { this.children.push(...hijos); },
        set innerHTML(_) { assert.fail('No se permite interpretar HTML'); } }) };
    const nodo = contenidoResultado(describirResultado({ ...exito, autor: '<script>dato</script>' }, config), documento);
    assert.equal(nodo.children[1].children[4].children[1].textContent, '<script>dato</script>');
});

test('DUX-07 integra sólo conformidad y resultados sobre las rutas y recursos existentes', () => {
    const vista = readFileSync(new URL('../../resources/views/historia-clinica/revision-previa.blade.php', import.meta.url), 'utf8');
    assert.match(vista, /@submit\.prevent="enviar\(\$event, propuestaEditada\)"/);
    assert.match(vista, /name="confirmar" value="1" required x-model="conforme"/);
    assert.match(vista, /button type="submit" disabled :disabled="!conforme/);
    assert.match(vista, /x-cloak x-show="procesando" role="status" aria-live="polite"/);
    assert.match(vista, /@input="propuestaEditada = true; conforme = false"/);
    assert.match(vista, /data-retorno-edicion/);
    assert.match(vista, /x-show="!incorporacionConfirmada" role="status"/);
    const modulo = readFileSync(new URL('../../resources/js/historia-clinica/confirmacion-revision.js', import.meta.url), 'utf8');
    assert.doesNotMatch(modulo, /setTimeout|setInterval|localStorage|sessionStorage|console\./);
    assert.doesNotMatch(modulo, /import .*sweetalert2/);
});
