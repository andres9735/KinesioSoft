import test from 'node:test';
import assert from 'node:assert/strict';
import solicitarTurno, { sumarDias } from '../../resources/js/turnos/solicitar-turno.js';

const configuracion = {
    uuid: 'uuid-servidor-estable', maxMotivo: 500, fechaInicial: '2026-10-17',
    urlDisponibilidad: 'http://localhost/turnos/solicitar/disponibilidad', urlMisTurnos: '/turnos/mis-turnos',
    urlConfirmacion: 'http://localhost/turnos/solicitar',
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

function entornoConfirmacion() {
    const avisos = new Set(), feedback = [], destinos = [];
    globalThis.document = { querySelector: () => ({ content: 'token-meta-real' }) };
    globalThis.window = {
        addEventListener: (tipo, fn) => avisos.add(fn), removeEventListener: (tipo, fn) => avisos.delete(fn),
        location: { assign: (url) => destinos.push(url) },
        Swal: { fire: async (opciones) => { feedback.push(opciones); return { isConfirmed: true }; } },
    };
    return { avisos, feedback, destinos };
}
function enPaso4(fetchImpl) {
    const w = enPaso3(fetchImpl);
    w.slotsDisponibles = [slot()]; w.estadoDisponibilidad = 'success';
    w.seleccionarSlot(slot().inicio_at); w.paso = 4;
    w.actualizarAvisoAbandono();
    return w;
}
const exito = (replay = false) => json({ turno_id: 42, es_replay: replay, redirect_url: '/destino-entregado?turno=42' }, replay ? 200 : 201);
function captura(w) {
    return { uuid: w.claveIdempotencia, motivo: w.motivo, archivos: w.archivos.map((e) => e.archivo),
        profesional: w.kinesiologaId, slot: w.slotSeleccionado, ventana: { ...w.ventana } };
}

test('POST multipart exacto: UUID, motivo, profesional, inicio y sólo File vigentes A,C,D; sin campos derivados', async () => {
    entornoConfirmacion();
    let solicitud;
    const w = enPaso4(async (...args) => { solicitud = args; return exito(); });
    w.archivos = [];
    const files = ['A', 'B', 'C', 'D'].map((nombre) => new File([nombre], `${nombre}.pdf`, { type: 'application/pdf' }));
    w.agregarArchivos({ target: { files: files.slice(0, 3), value: '' } });
    w.quitarArchivo(w.archivos[1].id);
    w.agregarArchivos({ target: { files: files.slice(3), value: '' } });
    w.motivo = ' Motivo completo\ncon espacios ';
    await w.confirmarTurno();
    const [url, opciones] = solicitud, datos = opciones.body;
    assert.equal(url, configuracion.urlConfirmacion); assert.equal(opciones.method, 'POST');
    assert.equal(opciones.credentials, 'same-origin');
    assert.deepEqual(opciones.headers, { Accept: 'application/json', 'X-CSRF-TOKEN': 'token-meta-real' });
    assert.ok(datos instanceof FormData);
    assert.deepEqual([...new Set(datos.keys())], ['clave_idempotencia', 'motivo_solicitud', 'kinesiologa_id', 'inicio_at', 'adjuntos[]']);
    assert.equal(datos.get('clave_idempotencia'), configuracion.uuid);
    assert.equal(datos.get('motivo_solicitud'), w.motivo);
    assert.equal(datos.get('kinesiologa_id'), '1'); assert.equal(datos.get('inicio_at'), slot().inicio_at);
    assert.deepEqual(datos.getAll('adjuntos[]'), [files[0], files[2], files[3]]);
    for (const campo of ['fin_at', 'paciente_id', 'tipo', 'estado', 'es_replay', 'ruta_storage', 'mime_type']) assert.equal(datos.has(campo), false);
});

for (const replay of [false, true]) test(`éxito ${replay ? '200 replay' : '201 nuevo'}: sin adjuntos, un feedback normal, sin beforeunload y redirect backend`, async () => {
    const { avisos, feedback, destinos } = entornoConfirmacion();
    let posts = 0;
    const w = enPaso4(async (url, opciones) => { posts++; assert.equal(opciones.body.has('adjuntos[]'), false); return exito(replay); });
    w.archivos = [];
    assert.equal(avisos.size, 1);
    await w.confirmarTurno(); await w.confirmarTurno();
    assert.equal(w.consolidado, true); assert.equal(w.puedeConfirmar, false); assert.equal(posts, 1);
    assert.equal(avisos.size, 0); w.actualizarAvisoAbandono(); assert.equal(avisos.size, 0);
    assert.deepEqual(destinos, ['/destino-entregado?turno=42']);
    assert.equal(feedback.length, 1); assert.equal(feedback[0].icon, 'success');
    assert.equal(feedback[0].title, 'Turno registrado correctamente'); assert.equal(feedback[0].confirmButtonText, 'Entendido');
    assert.match(feedback[0].text, /Primera consulta.*Profesional A.*Pendiente/);
    assert.doesNotMatch(feedback[0].text, /duplicado|replay|email|SMS/);
});

test('doble click un POST: todas las mutaciones/navegaciones bloqueadas; beforeunload permanece hasta respuesta válida', async () => {
    const { avisos, feedback } = entornoConfirmacion(), pendiente = diferida();
    let posts = 0;
    const w = enPaso4(() => { posts++; return pendiente.promise; });
    const antes = captura(w);
    const envio = w.confirmarTurno(); await w.confirmarTurno();
    w.anterior(); await w.siguiente(); await w.volverAMisTurnos();
    w.seleccionarProfesional(2); await w.cambiarVentana(1); w.seleccionarSlot('otro');
    w.quitarArchivo(1); w.agregarArchivos({ target: { files: [new File(['a'], 'a.pdf')] } });
    await w.consultarDisponibilidad();
    assert.deepEqual(captura(w), antes); assert.equal(w.paso, 4); assert.equal(posts, 1);
    assert.equal(w.enviando, true); assert.equal(avisos.size, 1); assert.equal(feedback.length, 0);
    let advertencia = false; [...avisos][0]({ preventDefault() { advertencia = true; } }); assert.equal(advertencia, true);
    pendiente.resolve(exito()); await envio; assert.equal(w.enviando, false);
});

test('fallback sin Swal o con feedback fallido conserva éxito y permite redirect', async () => {
    for (const swal of [undefined, { fire: async () => { throw new Error('UI'); } }]) {
        const { destinos } = entornoConfirmacion(); window.Swal = swal;
        const w = enPaso4(async () => exito()); await w.confirmarTurno();
        assert.equal(w.consolidado, true); assert.equal(w.resultadoIncierto, false); assert.equal(destinos.length, 1);
    }
});

test('D-01 bloqueante dirige a Mis turnos una vez sin doble abandono ni nueva UUID', async () => {
    const { feedback, destinos, avisos } = entornoConfirmacion();
    const w = enPaso4(async () => json({ code: 'primera_consulta_activa', message: 'No comparar' }, 409));
    const antes = captura(w); await w.confirmarTurno(); await w.confirmarTurno();
    assert.deepEqual(captura(w), antes); assert.equal(feedback.length, 1); assert.equal(feedback[0].icon, 'info');
    assert.deepEqual(destinos, [configuracion.urlMisTurnos]); assert.equal(avisos.size, 0); assert.equal(w.consolidado, false);
});

test('409 profesional conserva captura y UUID, invalida profesional/slots y no permite reselección obsoleta', async () => {
    const { destinos } = entornoConfirmacion();
    const w = enPaso4(async () => json({ code: 'profesional_no_elegible', message: 'No comparar' }, 409));
    const file = w.archivos[0].archivo;
    await w.confirmarTurno();
    assert.equal(w.paso, 2); assert.equal(w.kinesiologaId, null); assert.equal(w.slotSeleccionado, null);
    assert.deepEqual(w.slotsDisponibles, []); assert.equal(w.archivos[0].archivo, file);
    assert.equal(w.claveIdempotencia, configuracion.uuid); assert.equal(w.motivo, 'Motivo conservado');
    w.seleccionarProfesional(1); assert.equal(w.kinesiologaId, null);
    w.seleccionarProfesional(2); assert.equal(w.kinesiologaId, 2); assert.deepEqual(destinos, []);
});

test('409 slot: conserva ventana/profesional/captura, limpia selección y refresca con mecanismo vigente', async () => {
    entornoConfirmacion(); let gets = 0;
    const w = enPaso4(async (url, opciones) => {
        if (opciones.method === 'POST') return json({ code: 'slot_no_disponible', message: 'Irrelevante' }, 409);
        gets++; return json({ slots: [slot('2026-10-17', '11:00:00', '11:45:00')] });
    });
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(gets, 1); assert.equal(w.paso, 3); assert.equal(w.slotSeleccionado, null);
    assert.deepEqual(captura(w), { ...antes, slot: null }); assert.equal(w.estadoDisponibilidad, 'success');
    assert.equal(w.mensajeConfirmacion, 'Ese horario acaba de dejar de estar disponible. Elegí otro horario.');
    assert.equal(w.enviando, false);
});

test('409 sin code y UUID 422 son integración bloqueada, no otro conflicto ni UUID nueva', async () => {
    for (const response of [() => json({ message: 'slot_no_disponible' }, 409), () => json({ errors: { clave_idempotencia: ['Inválida'] } }, 422)]) {
        const { destinos, feedback } = entornoConfirmacion();
        const w = enPaso4(async () => response()), antes = captura(w);
        await w.confirmarTurno(); assert.deepEqual(captura(w), antes); assert.equal(w.confirmacionBloqueada, true);
        assert.equal(w.puedeConfirmar, false); assert.equal(w.paso, 4); assert.equal(feedback.length, 0); assert.deepEqual(destinos, []);
    }
});

test('422 motivo y adjuntos individuales se muestran separados: conservan File y sólo marcan índice de esa tentativa', async () => {
    entornoConfirmacion();
    const w = enPaso4(async () => json({ errors: { motivo_solicitud: ['Motivo servidor'], 'adjuntos.1': ['Contenido inválido'] } }, 422));
    w.archivos.push({ id: 7, archivo: new File(['b'], 'b.pdf', { type: 'application/pdf' }), error: '' });
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(w.paso, 1); assert.deepEqual(captura(w), antes);
    assert.equal(w.erroresServidor.motivo_solicitud, 'Motivo servidor'); assert.equal(w.errorMotivo, '');
    assert.equal(w.archivos[0].errorServidor, undefined); assert.equal(w.archivos[1].errorServidor, 'Contenido inválido');
    assert.equal(w.archivosValidos.length, 1); await w.siguiente(); assert.equal(w.paso, 1);
    w.corregirMotivo(); assert.equal(w.erroresServidor.motivo_solicitud, undefined);
    assert.equal(w.archivos[1].errorServidor, 'Contenido inválido');
    w.quitarArchivo(7); await w.siguiente(); assert.equal(w.paso, 2);
    assert.equal(w.archivos[0].archivo, antes.archivos[0]);
});

test('422 adjuntos global conserva todos y no culpa un archivo; se limpia sólo al cambiar conjunto', async () => {
    entornoConfirmacion();
    const w = enPaso4(async () => json({ errors: { adjuntos: ['Total excesivo'] } }, 422)), antes = captura(w);
    await w.confirmarTurno(); assert.deepEqual(captura(w), antes); assert.equal(w.paso, 1);
    assert.equal(w.erroresServidor.adjuntos, 'Total excesivo'); assert.equal(w.erroresArchivos.length, 0);
    w.corregirMotivo(); assert.equal(w.erroresServidor.adjuntos, 'Total excesivo');
    w.quitarArchivo(1); assert.equal(w.erroresServidor.adjuntos, undefined);
});

for (const campo of ['kinesiologa_id', 'inicio_at']) test(`422 ${campo} recupera contexto correcto sin culpar motivo/archivos`, async () => {
    entornoConfirmacion();
    const w = enPaso4(async (url, opciones) => opciones.method === 'POST' ? json({ errors: { [campo]: ['Error campo'] } }, 422) : json({ slots: [] }));
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(w.paso, campo === 'kinesiologa_id' ? 2 : 3); assert.equal(w.slotSeleccionado, null);
    assert.equal(w.kinesiologaId, campo === 'kinesiologa_id' ? null : antes.profesional);
    assert.equal(w.motivo, antes.motivo); assert.equal(w.archivos[0].archivo, antes.archivos[0]);
    assert.equal(w.claveIdempotencia, antes.uuid); assert.equal(w.erroresServidor[campo], 'Error campo');
});

for (const [nombre, respuesta] of [
    ['red', () => { throw new TypeError('red'); }], ['500', () => json({}, 500)],
    ['JSON inválido', () => new Response('{', { status: 201 })], ['HTML', () => new Response('<html/>')],
    ['body incompleto', () => json({ turno_id: 42 }, 201)],
    ['id inválido', () => json({ turno_id: -1, es_replay: false, redirect_url: '/x' }, 201)],
    ['replay no boolean', () => json({ turno_id: 42, es_replay: 'true', redirect_url: '/x' })],
    ['redirect vacío', () => json({ turno_id: 42, es_replay: false, redirect_url: ' ' }, 201)],
]) test(`${nombre}: resultado incierto conserva todo; retry nueva FormData con misma UUID/File sin duplicados`, async () => {
    const { feedback, destinos, avisos } = entornoConfirmacion(), envios = [];
    const w = enPaso4(async (url, opciones) => { envios.push(opciones.body); return envios.length === 1 ? respuesta() : exito(true); });
    const antes = captura(w); await w.confirmarTurno();
    assert.deepEqual(captura(w), antes); assert.equal(w.resultadoIncierto, true); assert.equal(w.enviando, false);
    assert.equal(w.consolidado, false); assert.equal(w.puedeConfirmar, true); assert.equal(avisos.size, 1);
    assert.equal(feedback.length, 0); assert.deepEqual(destinos, []);
    await w.confirmarTurno(); assert.notEqual(envios[0], envios[1]);
    for (const datos of envios) {
        assert.equal(datos.get('clave_idempotencia'), antes.uuid);
        assert.deepEqual(datos.getAll('adjuntos[]'), antes.archivos);
    }
    assert.equal(w.consolidado, true); assert.equal(feedback.length, 1); assert.equal(destinos.length, 1);
});

for (const status of [401, 403, 419]) test(`sesión ${status}: no éxito/auto-retry, estado intacto y mensaje de autenticación`, async () => {
    const { destinos } = entornoConfirmacion(); let posts = 0;
    const w = enPaso4(async () => { posts++; return new Response('<html/>', { status }); }), antes = captura(w);
    await w.confirmarTurno(); await w.confirmarTurno();
    assert.deepEqual(captura(w), antes); assert.equal(posts, 1); assert.equal(w.confirmacionBloqueada, true);
    assert.match(w.mensajeConfirmacion, /sesión.*ingresar/); assert.deepEqual(destinos, []); assert.equal(w.enviando, false);
});

function observarFoco(w, ocultos = []) {
    const pendientes = [], focos = [];
    w.$nextTick = (fn) => pendientes.push(fn);
    w.$refs = new Proxy({}, { get: (_, nombre) => ({
        getClientRects: () => ocultos.includes(nombre) ? [] : [{}],
        focus: () => focos.push(nombre),
    }) });
    return { focos, flush: () => { while (pendientes.length) pendientes.shift()(); } };
}

test('foco de pasos 1→2→3→4 y volver; carga exitosa no solicita foco adicional', async () => {
    const w = preparar(async () => json({ slots: [slot()] })), f = observarFoco(w);
    await w.siguiente(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso2');
    w.seleccionarProfesional(1); await w.siguiente(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso3');
    assert.equal(f.focos.length, 2);
    w.seleccionarSlot(slot().inicio_at); await w.siguiente(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso4');
    w.anterior(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso3');
});

test('foco omite destino oculto y descarta una petición perteneciente a otro paso', () => {
    const w = preparar(), f = observarFoco(w, ['profesionalMovil']);
    w.paso = 2; w.enfocarPaso('profesionalMovil', 'profesionales'); f.flush();
    assert.deepEqual(f.focos, ['profesionales']);
    w.enfocarPaso(); w.paso = 3; f.flush(); assert.equal(f.focos.length, 1);
});

for (const [errores, destino] of [[{ motivo_solicitud: ['Corregí el motivo'] }, 'motivo'], [{ 'adjuntos.0': ['Archivo inválido'] }, 'documentacion']]) {
    test(`422 solicita foco en ${destino} al recuperar Paso 1`, async () => {
        entornoConfirmacion();
        const w = enPaso4(async () => json({ errors: errores }, 422)), f = observarFoco(w);
        await w.confirmarTurno(); f.flush(); assert.equal(w.paso, 1); assert.equal(f.focos.at(-1), destino);
    });
}

for (const [code, destino] of [['profesional_no_elegible', 'errorProfesional'], ['slot_no_disponible', 'mensajeConfirmacion']]) {
    test(`409 ${code}: foco contextual; refresh exitoso no lo roba`, async () => {
        entornoConfirmacion();
        const w = enPaso4(async (url, opciones) => opciones.method === 'POST' ? json({ code }, 409) : json({ slots: [slot()] }));
        const f = observarFoco(w); await w.confirmarTurno(); f.flush(); assert.deepEqual(f.focos, [destino]);
    });
}

test('error de disponibilidad solicita foco en el contexto que contiene Retry', async () => {
    const w = enPaso3(async () => { throw new TypeError('Red'); }), f = observarFoco(w);
    await w.consultarDisponibilidad(); f.flush(); assert.deepEqual(f.focos, ['errorDisponibilidad']);
});

test('Retry lleva foco a contexto visible antes de ocultar el error; success no vuelve a moverlo', async () => {
    const pendiente = diferida(), w = enPaso3(() => pendiente.promise), f = observarFoco(w);
    const refs = w.$refs;
    w.$refs = new Proxy(refs, { get: (target, nombre) => nombre === 'errorDisponibilidad'
        ? { ...target[nombre], contains: () => true } : target[nombre] });
    const consulta = w.consultarDisponibilidad(); f.flush();
    assert.deepEqual(f.focos, ['tituloPaso3']);
    pendiente.resolve(json({ slots: [slot()] })); await consulta; f.flush();
    assert.deepEqual(f.focos, ['tituloPaso3']);
});
