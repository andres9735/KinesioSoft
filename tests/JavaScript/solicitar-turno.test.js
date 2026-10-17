import test from 'node:test';
import assert from 'node:assert/strict';
import solicitarTurno, { sumarDias } from '../../resources/js/turnos/solicitar-turno.js';

const configuracion = {
    uuid: 'uuid-servidor-estable', maxMotivo: 500, fechaInicial: '2026-10-17',
    urlDisponibilidad: 'http://localhost/turnos/solicitar/disponibilidad', urlMisTurnos: '/turnos/mis-turnos',
    profesionales: [{ id: 1, nombre: 'Profesional A' }, { id: 2, nombre: 'Profesional B' }],
    limites: { maxArchivos: 5, maxBytesPorArchivo: 10485760, maxBytesTotal: 26214400, mimePermitidos: ['application/pdf'], extensionesPermitidas: ['pdf'] },
};
const slot = (fecha = '2026-10-17', hora = '10:00:00', fin = '10:45:00') => ({ inicio_at: `${fecha} ${hora}`, fin_at: `${fecha} ${fin}` });
const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const diferida = () => {
    let resolve, reject;
    const promise = new Promise((si, no) => { resolve = si; reject = no; });
    return { promise, resolve, reject };
};
function preparar(fetchImpl) {
    const w = solicitarTurno(configuracion, { fetchImpl });
    w.$refs = new Proxy({}, { get: () => ({ focus() {} }) });
    w.$nextTick = (fn) => fn();
    w.$watch = () => {};
    w.motivo = 'Motivo conservado';
    w.archivos = [{ id: 1, archivo: new File(['%PDF'], 'estudio.pdf', { type: 'application/pdf' }), error: '' }];
    w.init();
    return w;
}
function enPaso3(fetchImpl) {
    const w = preparar(fetchImpl);
    w.seleccionarProfesional(1);
    w.paso = 3;
    return w;
}

test('fecha civil: siete fechas inclusivas, fin de mes, año y bisiesto sin convertir las horas', () => {
    assert.equal(sumarDias('2026-10-17', 6), '2026-10-23');
    assert.equal(sumarDias('2026-12-29', 6), '2027-01-04');
    assert.equal(sumarDias('2028-02-27', 2), '2028-02-29');
    const w = preparar(() => assert.fail('No debe consultar'));
    assert.deepEqual(w.ventana, { desde: '2026-10-17', hasta: '2026-10-23' });
    assert.match(w.fechaLegible('2026-10-17'), /17.*octubre.*2026/);
    assert.equal(w.horaLegible('2026-10-17 18:30:00'), '18:30');
});

test('la captura no consulta hasta entrar al Paso 3; GET y parámetros exactos', async () => {
    const llamadas = [];
    const w = preparar(async (...args) => { llamadas.push(args); return json({ slots: [slot()] }); });
    assert.equal(w.estadoDisponibilidad, 'idle');
    await w.siguiente();
    w.seleccionarProfesional(1);
    assert.equal(llamadas.length, 0);
    await w.siguiente();
    assert.equal(w.paso, 3);
    assert.equal(llamadas.length, 1);
    const [url, opciones] = llamadas[0];
    assert.deepEqual(Object.fromEntries(new URL(url).searchParams), { kinesiologa_id: '1', desde: '2026-10-17', hasta: '2026-10-23' });
    assert.equal(opciones.headers.Accept, 'application/json');
    assert.equal(opciones.method ?? 'GET', 'GET');
    assert.equal(opciones.body, undefined);
    assert.equal(w.estadoDisponibilidad, 'success');
});

test('loading visible en estado antes de resolver y selección sólo desde el resultado vigente', async () => {
    const pendiente = diferida();
    const w = enPaso3(() => pendiente.promise);
    const consulta = w.consultarDisponibilidad();
    assert.equal(w.estadoDisponibilidad, 'loading');
    assert.equal(w.puedeRevisar, false);
    pendiente.resolve(json({ slots: [{ ...slot(), paciente_id: 999, motivo: 'No renderizar' }] }));
    await consulta;
    assert.deepEqual(w.slotsDisponibles, [slot()]);
    w.seleccionarSlot('2026-10-17 12:00:00');
    assert.equal(w.slotSeleccionado, null);
    w.seleccionarSlot(slot().inicio_at);
    assert.deepEqual(w.slotSeleccionado, slot());
    await w.siguiente();
    assert.equal(w.paso, 4);
});

test('vacío 200 se distingue de error y no permite avanzar', async () => {
    const w = enPaso3(async () => json({ slots: [] }));
    await w.consultarDisponibilidad();
    assert.equal(w.estadoDisponibilidad, 'empty');
    assert.equal(w.errorDisponibilidad, '');
    await w.siguiente();
    assert.equal(w.paso, 3);
});

test('red y retry conservan profesional, ventana, motivo, archivo y UUID', async () => {
    const consultas = [];
    const w = enPaso3(async (url) => {
        consultas.push(url);
        if (consultas.length === 1) throw new TypeError('Falla de red');
        return json({ slots: [slot()] });
    });
    const archivo = w.archivosValidos[0];
    await w.consultarDisponibilidad();
    assert.equal(w.estadoDisponibilidad, 'error');
    assert.ok(w.errorDisponibilidad);
    await w.consultarDisponibilidad();
    assert.equal(w.estadoDisponibilidad, 'success');
    assert.equal(consultas[0], consultas[1]);
    assert.equal(w.kinesiologaId, 1);
    assert.equal(w.motivo, 'Motivo conservado');
    assert.equal(w.archivosValidos[0], archivo);
    assert.equal(w.claveIdempotencia, configuracion.uuid);
});

test('ventanas avanzan siete días sin horizonte de 31 días y no retroceden antes de la inicial', async () => {
    const fechas = [];
    const w = enPaso3(async (url) => { fechas.push(Object.fromEntries(new URL(url).searchParams)); return json({ slots: [] }); });
    await w.cambiarVentana(-1);
    assert.equal(fechas.length, 0);
    for (let i = 1; i <= 12; i++) {
        await w.cambiarVentana(1);
        assert.equal(fechas.at(-1).desde, sumarDias(configuracion.fechaInicial, i * 7));
        assert.equal(fechas.at(-1).hasta, sumarDias(fechas.at(-1).desde, 6));
    }
    await w.cambiarVentana(-1);
    assert.equal(w.ventana.desde, sumarDias(configuracion.fechaInicial, 77));
});

test('refresh y cambio de ventana invalidan selección antes del request', async () => {
    const w = enPaso3(async () => json({ slots: [slot()] }));
    await w.consultarDisponibilidad();
    w.seleccionarSlot(slot().inicio_at);
    const refresh = w.consultarDisponibilidad();
    assert.equal(w.slotSeleccionado, null);
    await refresh;
    assert.equal(w.slotSeleccionado, null);
    w.seleccionarSlot(slot().inicio_at);
    await w.cambiarVentana(1);
    assert.equal(w.slotSeleccionado, null);
    // La respuesta del fake pertenece a la semana anterior: se rechaza como malformada.
    assert.equal(w.estadoDisponibilidad, 'error');
});

test('cambio profesional cancela y conserva la semana y captura local', async () => {
    const pendiente = diferida();
    let signal;
    const w = enPaso3((url, opciones) => { signal = opciones.signal; return pendiente.promise; });
    const archivo = w.archivosValidos[0];
    const consulta = w.consultarDisponibilidad();
    w.seleccionarProfesional(2);
    assert.equal(signal.aborted, true);
    assert.equal(w.slotSeleccionado, null);
    assert.deepEqual(w.slotsDisponibles, []);
    assert.equal(w.estadoDisponibilidad, 'idle');
    assert.equal(w.ventana.desde, configuracion.fechaInicial);
    assert.equal(w.archivosValidos[0], archivo);
    assert.equal(w.claveIdempotencia, configuracion.uuid);
    pendiente.resolve(json({ slots: [slot()] }));
    await consulta;
    assert.deepEqual(w.slotsDisponibles, []);
});

test('A llega después de B: no restaura resultados ni estado anterior aunque ignore el aborto', async () => {
    const a = diferida(), b = diferida();
    let llamadas = 0;
    const w = enPaso3(() => (++llamadas === 1 ? a.promise : b.promise));
    const primera = w.consultarDisponibilidad();
    const segunda = w.cambiarVentana(1);
    const actual = slot('2026-10-24', '18:00:00', '18:30:00');
    b.resolve(json({ slots: [actual] }));
    await segunda;
    a.resolve(json({ slots: [slot()] }));
    await primera;
    assert.deepEqual(w.slotsDisponibles, [actual]);
    assert.equal(w.estadoDisponibilidad, 'success');
});

test('finally de A no apaga loading de B; error viejo tampoco lo reemplaza', async () => {
    const a = diferida(), b = diferida();
    let llamadas = 0;
    const w = enPaso3(() => (++llamadas === 1 ? a.promise : b.promise));
    const primera = w.consultarDisponibilidad();
    const segunda = w.consultarDisponibilidad();
    a.reject(new Error('Falla vieja'));
    await primera;
    assert.equal(w.estadoDisponibilidad, 'loading');
    assert.equal(w.errorDisponibilidad, '');
    b.resolve(json({ slots: [] }));
    await segunda;
    assert.equal(w.estadoDisponibilidad, 'empty');
});

test('invalidación también protege una lectura JSON diferida y un 409 viejo', async () => {
    const contenido = diferida();
    let llamadas = 0;
    const w = enPaso3(async () => ++llamadas === 1 ? { status: 409, json: () => contenido.promise } : json({ slots: [slot()] }));
    const primera = w.consultarDisponibilidad();
    await Promise.resolve();
    w.seleccionarProfesional(2);
    await w.consultarDisponibilidad();
    contenido.resolve({ code: 'profesional_no_elegible' });
    await primera;
    assert.equal(w.kinesiologaId, 2);
    assert.equal(w.paso, 3);
    assert.equal(w.estadoDisponibilidad, 'success');
});

test('abort intencional y destroy son silenciosos e impiden mutaciones tardías', async () => {
    const pendiente = diferida();
    let signal;
    const w = enPaso3((url, opciones) => { signal = opciones.signal; return pendiente.promise; });
    const consulta = w.consultarDisponibilidad();
    w.anterior();
    assert.equal(signal.aborted, true);
    pendiente.reject(new DOMException('Abortado', 'AbortError'));
    await consulta;
    assert.equal(w.errorDisponibilidad, '');
    assert.equal(w.paso, 2);
    const otro = diferida();
    const destruido = enPaso3(() => otro.promise);
    const tardia = destruido.consultarDisponibilidad();
    destruido.destroy();
    otro.resolve(json({ slots: [slot()] }));
    await tardia;
    assert.deepEqual(destruido.slotsDisponibles, []);
});

for (const [nombre, response] of [
    ['JSON inválido', () => new Response('{')],
    ['HTML', () => new Response('<html>login</html>')],
    ['sin slots', () => json({})],
    ['slots no array', () => json({ slots: {} })],
    ['slot sin fin', () => json({ slots: [{ inicio_at: slot().inicio_at }] })],
    ['hora inválida', () => json({ slots: [slot('2026-10-17', '25:00:00')] })],
    ['fecha inválida', () => json({ slots: [slot('2026-02-30')] })],
    ['HTTP 401', () => json({}, 401)], ['HTTP 403', () => json({}, 403)],
    ['HTTP 422', () => json({}, 422)], ['HTTP 500', () => json({}, 500)],
]) {
    test(`${nombre} produce error y nunca empty`, async () => {
        const w = enPaso3(async () => response());
        await w.consultarDisponibilidad();
        assert.equal(w.estadoDisponibilidad, 'error');
        assert.deepEqual(w.slotsDisponibles, []);
        assert.equal(w.slotSeleccionado, null);
    });
}

test('409 profesional no elegible recupera Paso 2 preservando motivo, archivos y UUID', async () => {
    const w = enPaso3(async () => json({ code: 'profesional_no_elegible', message: 'Texto irrelevante' }, 409));
    const archivo = w.archivosValidos[0];
    await w.consultarDisponibilidad();
    assert.equal(w.paso, 2);
    assert.equal(w.kinesiologaId, null);
    assert.equal(w.slotSeleccionado, null);
    assert.deepEqual(w.slotsDisponibles, []);
    assert.equal(w.estadoDisponibilidad, 'idle');
    assert.ok(w.errorProfesional);
    assert.equal(w.archivosValidos[0], archivo);
    assert.equal(w.motivo, 'Motivo conservado');
    assert.equal(w.claveIdempotencia, configuracion.uuid);
});

test('Paso 4 a Paso 3 conserva slot y no dispara refresh', async () => {
    let llamadas = 0;
    const w = enPaso3(async () => { llamadas++; return json({ slots: [slot()] }); });
    await w.consultarDisponibilidad();
    w.seleccionarSlot(slot().inicio_at);
    await w.siguiente();
    w.anterior();
    assert.equal(w.paso, 3);
    assert.deepEqual(w.slotSeleccionado, slot());
    assert.equal(llamadas, 1);
    assert.equal(w.claveIdempotencia, configuracion.uuid);
});
