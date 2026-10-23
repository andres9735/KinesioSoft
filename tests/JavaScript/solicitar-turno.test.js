import test from 'node:test';
import assert from 'node:assert/strict';
import solicitarTurno, { sumarDias } from '../../resources/js/turnos/solicitar-turno.js';

const configuracion = {
    uuid: 'uuid-servidor-estable', maxMotivo: 500, fechaInicial: '2026-10-17',
    urlDisponibilidad: 'http://localhost/turnos/solicitar/disponibilidad', urlMisTurnos: '/turnos/mis-turnos',
    urlProfesionalesDisponibles: 'http://localhost/turnos/solicitar/profesionales-disponibles',
    urlConfirmacion: 'http://localhost/turnos/solicitar',
    limites: { maxArchivos: 5, maxBytesPorArchivo: 10485760, maxBytesTotal: 26214400, mimePermitidos: ['application/pdf'], extensionesPermitidas: ['pdf'] },
};
const slot = (fecha = '2026-10-17', hora = '10:00:00', fin = '10:45:00') => ({ inicio_at: `${fecha} ${hora}`, fin_at: `${fecha} ${fin}` });
const ventanaInicial = { desde: '2026-10-17', hasta: '2026-10-23' };
const agregado = (inicios = [slot().inicio_at], ventana = ventanaInicial) => ({ ...ventana,
    dias: [...new Set(inicios.map((inicio) => inicio.slice(0, 10)))].map((fecha) => ({ fecha,
        horarios: inicios.filter((inicio) => inicio.startsWith(fecha)).map((inicio_at) => ({ inicio_at })) })) });
const profesionales = (inicio = slot().inicio_at) => ({ inicio_at: inicio, profesionales: [
    { kinesiologa_id: 1, nombre: 'Profesional A', fin_at: `${inicio.slice(0, 10)} 10:30:00` },
    { kinesiologa_id: 2, nombre: 'Profesional B', fin_at: `${inicio.slice(0, 10)} 10:45:00` },
] });
const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const diferida = () => {
    let resolve, reject;
    const promise = new Promise((si, no) => { resolve = si; reject = no; });
    return { promise, resolve, reject };
};
function preparar(fetchImpl = () => assert.fail('Consulta no esperada')) {
    const w = solicitarTurno(configuracion, { fetchImpl });
    w.$refs = new Proxy({}, { get: () => ({ focus() {} }) });
    // Los tests unitarios de estado consultan explícitamente; el ciclo inicial completo se prueba aparte.
    w.$nextTick = () => {}; w.$watch = () => {};
    w.motivo = 'Motivo conservado';
    w.archivos = [{ id: 100, archivo: new File(['%PDF'], 'estudio.pdf', { type: 'application/pdf' }), descripcion: 'Descripción conservada', error: '' }];
    w.init(); w.$nextTick = (fn) => fn(); return w;
}
function conInicio(fetchImpl) {
    const w = preparar(fetchImpl);
    w.diasDisponibles = agregado([slot().inicio_at, '2026-10-17 10:30:00', '2026-10-18 10:00:00']).dias;
    w.ventanaConsultada = { ...ventanaInicial }; w.estadoDisponibilidad = 'success';
    w.seleccionarFecha('2026-10-17'); w.seleccionarInicio(slot().inicio_at); return w;
}
function seleccionCompleta(fetchImpl) {
    const w = conInicio(fetchImpl);
    w.profesionales = profesionales().profesionales; w.inicioProfesionales = w.inicioSeleccionado; w.estadoProfesionales = 'success';
    w.seleccionarProfesional(1); return w;
}
function capturaLocal(w) {
    return { motivo: w.motivo, uuid: w.claveIdempotencia, archivos: w.archivos.map((entrada) => ({ ...entrada })) };
}

test('estado inicial V2 sin catálogo, duración ni intervalo universal; UUID del servidor', () => {
    const w = solicitarTurno(configuracion);
    assert.deepEqual(w.pasos, ['Fecha y horario', 'Elegir profesional', 'Motivo y documentación', 'Revisar y confirmar']);
    assert.equal(w.paso, 1); assert.equal(w.claveIdempotencia, configuracion.uuid);
    assert.deepEqual(w.ventana, ventanaInicial); assert.equal(w.ventanaConsultada, null);
    assert.deepEqual(w.diasDisponibles, []); assert.deepEqual(w.profesionales, []);
    assert.equal(w.fechaSeleccionada, null); assert.equal(w.inicioSeleccionado, null);
    assert.equal(w.profesionalSeleccionada, null); assert.equal(w.intervaloSeleccionado, null);
    assert.equal(w.estadoDisponibilidad, 'idle'); assert.equal(w.estadoProfesionales, 'idle');
    assert.equal('slotSeleccionado' in w, false); assert.equal('finSeleccionado' in w, false);
});

test('fecha civil y ventanas de siete días sin horizonte máximo de reserva', async () => {
    assert.equal(sumarDias('2026-12-29', 6), '2027-01-04'); assert.equal(sumarDias('2028-02-27', 2), '2028-02-29');
    const w = preparar(async (url) => json(agregado([], Object.fromEntries(new URL(url).searchParams))));
    await w.cambiarVentana(-1); assert.deepEqual(w.ventana, ventanaInicial);
    for (let i = 1; i <= 12; i++) {
        await w.cambiarVentana(1); assert.equal(w.ventana.desde, sumarDias(configuracion.fechaInicial, i * 7));
        assert.deepEqual(w.ventanaConsultada, w.ventana); assert.equal(w.estadoDisponibilidad, 'empty');
    }
    assert.match(w.fechaLegible('2026-10-17'), /17.*octubre.*2026/);
    assert.equal(w.horaLegible('2026-10-17 10:00:37'), '10:00');
});

test('cadena V2: GET fechas sin profesional y GET profesionales sólo con inicio exacto y finales propios', async () => {
    const llamadas = [], inicio = '2026-10-17 10:00:37';
    const w = preparar(async (url, opciones) => { llamadas.push([new URL(url), opciones]); return json(llamadas.length === 1 ? agregado([inicio]) : profesionales(inicio)); });
    await w.siguiente(); assert.equal(w.paso, 1); assert.equal(llamadas.length, 0);
    await w.consultarDisponibilidad(); assert.deepEqual(Object.fromEntries(llamadas[0][0].searchParams), ventanaInicial);
    assert.equal(llamadas[0][0].pathname, '/turnos/solicitar/disponibilidad');
    w.seleccionarFecha('2026-10-17'); w.seleccionarInicio(inicio); assert.equal(w.intervaloSeleccionado, null);
    await w.siguiente(); assert.equal(w.paso, 2);
    assert.deepEqual(Object.fromEntries(llamadas[1][0].searchParams), { inicio_at: inicio });
    assert.equal(llamadas[1][0].pathname, '/turnos/solicitar/profesionales-disponibles');
    for (const [, opciones] of llamadas) {
        assert.equal(opciones.method ?? 'GET', 'GET'); assert.equal(opciones.body, undefined);
        assert.equal(opciones.headers.Accept, 'application/json'); assert.equal(opciones.cache, 'no-store');
    }
    w.seleccionarProfesional(1); assert.equal(w.intervaloSeleccionado.fin_at, '2026-10-17 10:30:00');
    w.seleccionarProfesional(2); assert.equal(w.intervaloSeleccionado.fin_at, '2026-10-17 10:45:00');
    assert.equal(w.inicioSeleccionado, inicio); assert.equal(w.claveIdempotencia, configuracion.uuid);
});

for (const cambio of ['fecha', 'inicio', 'profesional']) test(`invalidación por ${cambio} conserva captura y UUID`, () => {
    const w = seleccionCompleta(), antes = capturaLocal(w);
    if (cambio === 'fecha') { w.seleccionarFecha('2026-10-18'); assert.equal(w.inicioSeleccionado, null); }
    else if (cambio === 'inicio') { w.seleccionarInicio('2026-10-17 10:30:00'); assert.equal(w.fechaSeleccionada, '2026-10-17'); }
    else {
        w.seleccionarProfesional(2); assert.equal(w.fechaSeleccionada, '2026-10-17'); assert.equal(w.inicioSeleccionado, slot().inicio_at);
        assert.equal(w.profesionalSeleccionada.kinesiologa_id, 2);
    }
    if (cambio !== 'profesional') {
        assert.equal(w.kinesiologaId, null); assert.equal(w.intervaloSeleccionado, null);
        assert.deepEqual(w.profesionales, []); assert.equal(w.inicioProfesionales, null);
    }
    assert.deepEqual(capturaLocal(w), antes);
});

test('retroceder o reseleccionar la misma dependencia no borra ni consulta', async () => {
    const w = seleccionCompleta(), antes = captura(w); w.paso = 4;
    for (const paso of [3, 2, 1]) { w.anterior(); assert.equal(w.paso, paso); assert.deepEqual(captura(w), antes); }
    w.seleccionarFecha(w.fechaSeleccionada); w.seleccionarInicio(w.inicioSeleccionado);
    await w.siguiente(); await w.siguiente(); await w.siguiente(); assert.equal(w.paso, 4); assert.deepEqual(captura(w), antes);
});

test('navegación exige inicio vigente, profesional correcta y motivo/descripciones válidos', async () => {
    const w = conInicio(async () => json(profesionales()));
    w.inicioSeleccionado = null; await w.siguiente(); assert.equal(w.paso, 1);
    w.seleccionarInicio(slot().inicio_at); await w.siguiente(); assert.equal(w.paso, 2);
    await w.siguiente(); assert.equal(w.paso, 2); w.seleccionarProfesional(999); assert.equal(w.kinesiologaId, null);
    w.seleccionarProfesional(1); await w.siguiente(); assert.equal(w.paso, 3);
    for (const motivo of [' ', 'x'.repeat(501)]) { w.motivo = motivo; w.corregirMotivo(); await w.siguiente(); assert.equal(w.paso, 3); }
    w.motivo = 'Motivo'; w.corregirMotivo();
    for (const descripcion of [' ', 'x'.repeat(501)]) { w.corregirDescripcion(100, descripcion); await w.siguiente(); assert.equal(w.paso, 3); }
    w.corregirDescripcion(100, '  ' + 'x'.repeat(500) + '  '); await w.siguiente(); assert.equal(w.paso, 4); assert.equal(w.puedeConfirmar, true);
});

test('captura no invalida disponibilidad; quitar/reemplazar mantiene identidad y descripción asociada', () => {
    const w = seleccionCompleta(), inicio = w.inicioSeleccionado, profesional = w.profesionalSeleccionada;
    w.motivo = 'Otro motivo'; w.corregirMotivo();
    w.agregarArchivos({ target: { files: [new File(['a'], 'a.pdf', { type: 'application/pdf' }), new File(['b'], 'b.pdf', { type: 'application/pdf' })] } });
    const [a, b] = w.archivos.slice(1); w.corregirDescripcion(a.id, 'A'); w.corregirDescripcion(b.id, 'B');
    w.quitarArchivo(a.id); assert.equal(w.archivos.find((e) => e.id === a.id), undefined);
    assert.equal(w.archivos.find((e) => e.id === b.id).descripcion, 'B');
    w.reemplazarArchivo(b.id, new File(['c'], 'c.pdf', { type: 'application/pdf' }));
    assert.notEqual(w.archivos.at(-1).id, b.id); assert.equal(w.archivos.at(-1).descripcion, '');
    assert.equal(w.inicioSeleccionado, inicio); assert.equal(w.profesionalSeleccionada, profesional); assert.equal(w.claveIdempotencia, configuracion.uuid);
});

test('rechazo individual no cuenta ni bloquea archivos válidos; límites salen de config', async () => {
    const w = seleccionCompleta(); w.paso = 3;
    w.agregarArchivos({ target: { files: [new File(['x'], 'falso.exe'), new File(['x'], 'bueno.pdf', { type: 'application/pdf' })] } });
    assert.equal(w.erroresSeleccionArchivos.length, 1); assert.equal(w.archivos.length, 2); assert.equal(w.totalBytes, 5);
    w.corregirDescripcion(w.archivos.at(-1).id, 'Válido'); await w.siguiente(); assert.equal(w.paso, 4);
    const limitado = solicitarTurno({ ...configuracion, limites: { ...configuracion.limites, maxArchivos: 1, maxBytesPorArchivo: 3, maxBytesTotal: 3 } });
    limitado.agregarArchivos({ target: { files: [new File(['1234'], 'grande.pdf', { type: 'application/pdf' })] } });
    assert.equal(limitado.archivos.length, 0);
    limitado.agregarArchivos({ target: { files: [new File(['12'], 'a.pdf', { type: 'application/pdf' }), new File(['12'], 'b.pdf', { type: 'application/pdf' })] } });
    assert.equal(limitado.erroresGeneralesArchivos.length, 2);
});

for (const tipo of ['disponibilidad', 'profesionales']) {
    const crear = (fetchImpl) => tipo === 'disponibilidad' ? preparar(fetchImpl) : conInicio(fetchImpl);
    const consultar = (w) => tipo === 'disponibilidad' ? w.consultarDisponibilidad() : w.cargarProfesionalesDisponibles();
    const estado = (w) => tipo === 'disponibilidad' ? w.estadoDisponibilidad : w.estadoProfesionales;
    const vacio = () => tipo === 'disponibilidad' ? agregado([]) : { inicio_at: slot().inicio_at, profesionales: [] };
    test(`${tipo}: loading independiente; 200 vacío consultado no es error ni no consultado`, async () => {
        const pendiente = diferida(), w = crear(() => pendiente.promise), antes = capturaLocal(w);
        const consulta = consultar(w); assert.equal(estado(w), 'loading');
        assert.equal(tipo === 'disponibilidad' ? w.estadoProfesionales : w.estadoDisponibilidad, tipo === 'disponibilidad' ? 'idle' : 'success');
        pendiente.resolve(json(vacio())); await consulta; assert.equal(estado(w), 'empty'); assert.deepEqual(capturaLocal(w), antes);
        if (tipo === 'disponibilidad') assert.deepEqual(w.ventanaConsultada, ventanaInicial);
        else assert.equal(w.inicioSeleccionado, slot().inicio_at);
    });
    for (const [nombre, respuesta] of [
        ['red', () => { throw new TypeError('privado'); }], ['JSON', () => new Response('{')], ['HTML', () => new Response('<html/>')],
        ['body inválido', () => json({})], ['401', () => json({}, 401)], ['403', () => json({}, 403)],
        ['422', () => json({}, 422)], ['500', () => json({}, 500)], ['409', () => json({ code: 'profesional_no_elegible' }, 409)],
    ]) test(`${tipo}: ${nombre} es error distinguible y conserva captura`, async () => {
        const w = crear(async () => respuesta()), antes = capturaLocal(w); await consultar(w);
        assert.equal(estado(w), 'error'); assert.deepEqual(capturaLocal(w), antes);
        assert.equal(w.tipoErrorDisponibilidad ?? w.tipoErrorProfesionales, nombre === '422' ? 'validacion' : 'tecnico');
        if (tipo === 'profesionales') { assert.equal(w.inicioSeleccionado, slot().inicio_at); assert.equal(w.profesionalSeleccionada, null); }
    });
    test(`${tipo}: destroy aborta e impide respuestas tardías`, async () => {
        const pendiente = diferida(); let signal;
        const w = crear((url, opciones) => { signal = opciones.signal; return pendiente.promise; });
        const consulta = consultar(w); w.destroy(); assert.equal(signal.aborted, true);
        pendiente.resolve(json(vacio())); await consulta; assert.equal(estado(w), 'loading'); assert.deepEqual(w.profesionales, []);
    });
}

test('ventana B prevalece sobre JSON y finally tardíos de A', async () => {
    const a = diferida(), b = diferida(); let llamadas = 0;
    const w = preparar(async () => ++llamadas === 1 ? { status: 200, json: () => a.promise } : b.promise);
    const primera = w.consultarDisponibilidad(); await Promise.resolve(); const segunda = w.cambiarVentana(1);
    a.resolve(agregado()); await primera; assert.equal(w.estadoDisponibilidad, 'loading');
    b.resolve(json(agregado(['2026-10-24 10:00:00'], w.ventana))); await segunda;
    assert.equal(w.diasDisponibles[0].fecha, '2026-10-24'); assert.deepEqual(w.ventanaConsultada, w.ventana);
});

test('inicio B prevalece sobre resultado/error tardío de A aunque ignore aborto', async () => {
    for (const fallo of [false, true]) {
        const a = diferida(), b = diferida(); let llamadas = 0, signal;
        const w = conInicio((url, opciones) => { if (++llamadas === 1) { signal = opciones.signal; return a.promise; } return b.promise; });
        const primera = w.cargarProfesionalesDisponibles(); w.seleccionarInicio('2026-10-17 10:30:00'); assert.equal(signal.aborted, true);
        const segunda = w.cargarProfesionalesDisponibles();
        const actual = { inicio_at: w.inicioSeleccionado, profesionales: [{ kinesiologa_id: 2, nombre: 'B', fin_at: '2026-10-17 11:15:00' }] };
        b.resolve(json(actual)); await segunda; w.seleccionarProfesional(2);
        if (fallo) a.reject(new Error('Falla antigua')); else a.resolve(json(profesionales()));
        await primera; assert.deepEqual(w.profesionales, actual.profesionales); assert.equal(w.kinesiologaId, 2);
        assert.equal(w.intervaloSeleccionado.fin_at, '2026-10-17 11:15:00'); assert.equal(w.estadoProfesionales, 'success');
    }
});

test('cambiar fecha cancela profesionales; JSON y finally viejo no alteran loading nuevo', async () => {
    const a = diferida(), b = diferida(); let llamadas = 0;
    const w = conInicio(async () => ++llamadas === 1 ? { status: 200, json: () => a.promise } : b.promise);
    const primera = w.cargarProfesionalesDisponibles(); await Promise.resolve();
    w.seleccionarFecha('2026-10-18'); w.seleccionarInicio('2026-10-18 10:00:00');
    const segunda = w.cargarProfesionalesDisponibles(); a.resolve(profesionales()); await primera;
    assert.equal(w.estadoProfesionales, 'loading'); assert.equal(w.errorConsultaProfesionales, '');
    b.resolve(json(profesionales(w.inicioSeleccionado))); await segunda; assert.equal(w.inicioProfesionales, '2026-10-18 10:00:00');
});

for (const payload of [
    { ...agregado(), hasta: '2026-10-24' }, agregado(['2026-10-17 25:00:00']), agregado(['2026-02-30 10:00:00']),
    agregado([slot().inicio_at, slot().inicio_at]), agregado(['2026-10-24 10:00:00']),
]) test('agregado malformado/duplicado/fuera de ventana no fabrica disponibilidad', async () => {
    const w = preparar(async () => json(payload)); await w.consultarDisponibilidad(); assert.equal(w.estadoDisponibilidad, 'error');
});
for (const payload of [
    { ...profesionales(), inicio_at: '2026-10-17 10:30:00' }, { ...profesionales(), profesionales: [{ id: 1, nombre: 'A' }] },
    { ...profesionales(), profesionales: [{ ...profesionales().profesionales[0], fin_at: slot().inicio_at }] },
    { ...profesionales(), profesionales: [profesionales().profesionales[0], profesionales().profesionales[0]] },
]) test('profesionales malformadas no permiten elegir identidad o intervalo ficticio', async () => {
    const w = conInicio(async () => json(payload)); await w.cargarProfesionalesDisponibles();
    assert.equal(w.estadoProfesionales, 'error'); w.seleccionarProfesional(1); assert.equal(w.intervaloSeleccionado, null);
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
    const w = seleccionCompleta(fetchImpl);
    w.paso = 4;
    w.actualizarAvisoAbandono();
    return w;
}
const exito = (replay = false) => json({ turno_id: 42, es_replay: replay, redirect_url: '/destino-entregado?turno=42' }, replay ? 200 : 201);
function captura(w) {
    return { uuid: w.claveIdempotencia, motivo: w.motivo, archivos: w.archivos.map((e) => e.archivo),
        profesional: w.kinesiologaId, slot: w.intervaloSeleccionado, ventana: { ...w.ventana } };
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
    for (const entrada of w.archivos) w.corregirDescripcion(entrada.id, 'Descripción ' + entrada.id);
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
    w.seleccionarProfesional(2); await w.cambiarVentana(1); w.seleccionarInicio('otro');
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

test('409 profesional conserva inicio y captura, pero exige recargar profesionales antes de seleccionar', async () => {
    const { destinos } = entornoConfirmacion();
    const w = enPaso4(async () => json({ code: 'profesional_no_elegible', message: 'No comparar' }, 409));
    const file = w.archivos[0].archivo;
    await w.confirmarTurno();
    assert.equal(w.paso, 2); assert.equal(w.kinesiologaId, null); assert.equal(w.intervaloSeleccionado, null);
    assert.deepEqual(w.profesionales, []); assert.equal(w.archivos[0].archivo, file);
    assert.equal(w.claveIdempotencia, configuracion.uuid); assert.equal(w.motivo, 'Motivo conservado');
    w.seleccionarProfesional(1); assert.equal(w.kinesiologaId, null);
    w.seleccionarProfesional(2); assert.equal(w.kinesiologaId, null); assert.deepEqual(destinos, []);
    assert.equal(w.inicioSeleccionado, slot().inicio_at);
});

test('409 slot retira el inicio y su profesional, conserva captura y vuelve al Paso 1', async () => {
    entornoConfirmacion(); let gets = 0;
    const w = enPaso4(async (url, opciones) => {
        if (opciones.method === 'POST') return json({ code: 'slot_no_disponible', message: 'Irrelevante' }, 409);
        gets++; return json(agregado(['2026-10-17 11:00:00']));
    });
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(gets, 1); assert.equal(w.paso, 1); assert.equal(w.intervaloSeleccionado, null);
    assert.deepEqual(captura(w), { ...antes, slot: null, profesional: null }); assert.equal(w.estadoDisponibilidad, 'success');
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
    const w = enPaso4(async () => json({ errors: { motivo_solicitud: ['Motivo servidor'], 'adjuntos.1.archivo': ['Contenido inválido'] } }, 422));
    w.archivos.push({ id: 7, archivo: new File(['b'], 'b.pdf', { type: 'application/pdf' }), error: '', descripcion: 'Descripción B' });
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(w.paso, 3); assert.deepEqual(captura(w), antes);
    assert.equal(w.erroresServidor.motivo_solicitud, 'Motivo servidor'); assert.equal(w.errorMotivo, '');
    assert.equal(w.archivos[0].errorServidor, undefined); assert.equal(w.archivos[1].errorServidor, 'Contenido inválido');
    assert.equal(w.archivosValidos.length, 1); await w.siguiente(); assert.equal(w.paso, 3);
    w.corregirMotivo(); assert.equal(w.erroresServidor.motivo_solicitud, undefined);
    assert.equal(w.archivos[1].errorServidor, 'Contenido inválido');
    w.quitarArchivo(7); await w.siguiente(); assert.equal(w.paso, 4);
    assert.equal(w.archivos[0].archivo, antes.archivos[0]);
});

test('422 adjuntos global conserva todos y no culpa un archivo; se limpia sólo al cambiar conjunto', async () => {
    entornoConfirmacion();
    const w = enPaso4(async () => json({ errors: { adjuntos: ['Total excesivo'] } }, 422)), antes = captura(w);
    await w.confirmarTurno(); assert.deepEqual(captura(w), antes); assert.equal(w.paso, 3);
    assert.equal(w.erroresServidor.adjuntos, 'Total excesivo'); assert.equal(w.erroresArchivos.length, 0);
    w.corregirMotivo(); assert.equal(w.erroresServidor.adjuntos, 'Total excesivo');
    w.quitarArchivo(100); assert.equal(w.erroresServidor.adjuntos, undefined);
});

for (const campo of ['kinesiologa_id', 'inicio_at']) test(`422 ${campo} recupera contexto correcto sin culpar motivo/archivos`, async () => {
    entornoConfirmacion();
    const w = enPaso4(async (url, opciones) => opciones.method === 'POST' ? json({ errors: { [campo]: ['Error campo'] } }, 422) : json(agregado([])));
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(w.paso, campo === 'kinesiologa_id' ? 2 : 1); assert.equal(w.intervaloSeleccionado, null);
    assert.equal(w.kinesiologaId, null);
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
    const w = conInicio(async () => json(profesionales())), f = observarFoco(w);
    await w.siguiente(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso2');
    w.seleccionarProfesional(1); await w.siguiente(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso3');
    assert.equal(f.focos.length, 2);
    await w.siguiente(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso4');
    w.anterior(); f.flush(); assert.equal(f.focos.at(-1), 'tituloPaso3');
});

test('foco omite destino oculto y descarta una petición perteneciente a otro paso', () => {
    const w = preparar(), f = observarFoco(w, ['profesionalMovil']);
    w.paso = 2; w.enfocarPaso('profesionalMovil', 'profesionales'); f.flush();
    assert.deepEqual(f.focos, ['profesionales']);
    w.enfocarPaso(); w.paso = 3; f.flush(); assert.equal(f.focos.length, 1);
});

for (const [errores, destino] of [[{ motivo_solicitud: ['Corregí el motivo'] }, 'motivo'], [{ 'adjuntos.0.archivo': ['Archivo inválido'] }, 'documentacion']]) {
    test(`422 solicita foco en ${destino} al recuperar Paso 3`, async () => {
        entornoConfirmacion();
        const w = enPaso4(async () => json({ errors: errores }, 422)), f = observarFoco(w);
        await w.confirmarTurno(); f.flush(); assert.equal(w.paso, 3); assert.equal(f.focos.at(-1), destino);
    });
}

for (const [code, destino] of [['profesional_no_elegible', 'errorProfesional'], ['slot_no_disponible', 'mensajeConfirmacion']]) {
    test(`409 ${code}: foco contextual; refresh exitoso no lo roba`, async () => {
        entornoConfirmacion();
        const w = enPaso4(async (url, opciones) => opciones.method === 'POST' ? json({ code }, 409) : json(agregado()));
        const f = observarFoco(w); await w.confirmarTurno(); f.flush(); assert.deepEqual(f.focos, [destino]);
    });
}

test('error de disponibilidad solicita foco en el contexto que contiene Retry', async () => {
    const w = preparar(async () => { throw new TypeError('Red'); }), f = observarFoco(w);
    await w.consultarDisponibilidad(); f.flush(); assert.deepEqual(f.focos, ['errorDisponibilidad']);
});

test('Retry lleva foco a contexto visible antes de ocultar el error; success no vuelve a moverlo', async () => {
    const pendiente = diferida(), w = preparar(() => pendiente.promise), f = observarFoco(w);
    const refs = w.$refs;
    w.$refs = new Proxy(refs, { get: (target, nombre) => nombre === 'errorDisponibilidad'
        ? { ...target[nombre], contains: () => true } : target[nombre] });
    const consulta = w.consultarDisponibilidad(); f.flush();
    assert.deepEqual(f.focos, ['tituloPaso1']);
    pendiente.resolve(json(agregado())); await consulta; f.flush();
    assert.deepEqual(f.focos, ['tituloPaso1']);
});

test('Paso 1: init agenda exactamente una consulta inicial, sin watcher/x-init duplicados ni profesionales anticipadas', async () => {
    const ticks = [], llamadas = []; let watchers = 0;
    const inicio = '2026-10-17 10:00:37';
    const w = solicitarTurno(configuracion, { fetchImpl: async (url) => {
        llamadas.push(new URL(url));
        return json(llamadas.length === 1 ? agregado([inicio]) : profesionales(inicio));
    } });
    w.$watch = () => watchers++; w.$refs = {}; w.$nextTick = (fn) => ticks.push(fn);
    w.init(); w.init(); assert.equal(ticks.length, 1); assert.equal(watchers, 1); assert.equal(llamadas.length, 0);
    await ticks.shift()(); assert.equal(llamadas.length, 1);
    assert.equal(llamadas[0].pathname, '/turnos/solicitar/disponibilidad');
    assert.deepEqual(Object.fromEntries(llamadas[0].searchParams), ventanaInicial);
    w.seleccionarFecha('2026-10-17'); assert.equal(w.inicioSeleccionado, null);
    w.seleccionarInicio(inicio); assert.equal(llamadas.length, 1); assert.equal(w.estadoProfesionales, 'idle');
    await w.siguiente(); assert.equal(w.paso, 2); assert.equal(llamadas.length, 2);
    assert.deepEqual(Object.fromEntries(llamadas[1].searchParams), { inicio_at: inicio });
});

test('Paso 1: calendario mensual deriva pasado, no consultada, sin disponibilidad, disponible y seleccionada', async () => {
    const w = preparar(async () => json(agregado()));
    assert.equal(w.estadoFecha('2026-10-16'), 'pasada');
    assert.equal(w.estadoFecha('2026-10-17'), 'no_consultada');
    assert.equal(w.estadoFecha('2026-10-24'), 'no_consultada');
    await w.consultarDisponibilidad();
    assert.equal(w.estadoFecha('2026-10-17'), 'disponible');
    assert.equal(w.estadoFecha('2026-10-18'), 'sin_disponibilidad');
    assert.equal(w.estadoFecha('2026-10-24'), 'no_consultada');
    w.seleccionarFecha('2026-10-18'); assert.equal(w.fechaSeleccionada, null);
    w.seleccionarFecha('2026-10-24'); assert.equal(w.fechaSeleccionada, null);
    w.seleccionarFecha('2026-10-16'); assert.equal(w.fechaSeleccionada, null);
    w.seleccionarFecha('2026-10-17'); assert.equal(w.estadoFecha('2026-10-17'), 'seleccionada');
    assert.equal(w.inicioSeleccionado, null); assert.equal(w.puedeElegirProfesional, false);
    assert.match(w.etiquetaFecha('2026-10-17'), /Seleccionada/);
    assert.match(w.etiquetaFecha('2026-10-24'), /No consultada/);
    assert.equal(w.fechasCalendario[0].fecha, '2026-09-28');
    assert.equal(w.fechasCalendario.at(-1).fecha, '2026-11-01');
    assert.equal(w.fechasCalendario.length % 7, 0); assert.match(w.mesCalendario, /octubre.*2026/);
});

for (const [desde, hasta, fecha] of [
    ['2026-10-31', '2026-11-06', '2026-11-06'],
    ['2026-12-29', '2027-01-04', '2027-01-04'],
    ['2028-02-27', '2028-03-04', '2028-02-29'],
]) test(`Paso 1: calendario contiene toda la ventana civil ${desde}/${hasta}, incluso al cruzar mes/año`, () => {
    const w = preparar(); w.ventana = { desde, hasta };
    const fechas = w.fechasCalendario.map((dia) => dia.fecha);
    assert.ok(fechas.includes(desde)); assert.ok(fechas.includes(hasta)); assert.ok(fechas.includes(fecha));
    assert.equal(fechas.length % 7, 0); assert.equal(new Set(fechas).size, fechas.length);
    for (let i = 1; i < fechas.length; i++) assert.equal(fechas[i], sumarDias(fechas[i - 1], 1));
});

test('Paso 1: elegir fecha muestra sólo sus horarios y nunca selecciona automáticamente inicio o profesional', async () => {
    const a = '2026-10-17 10:00:37', b = '2026-10-18 15:30:13'; let requests = 0;
    const w = preparar(async () => { requests++; return json(agregado([a, b])); });
    await w.consultarDisponibilidad(); const antes = capturaLocal(w);
    assert.deepEqual(w.horariosDisponibles, []);
    w.seleccionarFecha('2026-10-17'); assert.deepEqual(w.horariosDisponibles, [{ inicio_at: a }]);
    assert.equal(w.inicioSeleccionado, null); w.seleccionarInicio(b); assert.equal(w.inicioSeleccionado, null);
    w.seleccionarInicio(a); assert.equal(w.inicioSeleccionado, a); assert.equal(w.puedeElegirProfesional, true);
    w.seleccionarFecha('2026-10-18'); assert.equal(w.inicioSeleccionado, null);
    assert.deepEqual(w.horariosDisponibles, [{ inicio_at: b }]); assert.equal(w.profesionalSeleccionada, null);
    assert.deepEqual(capturaLocal(w), antes); assert.equal(requests, 1);
});

test('Paso 1: loading retira resultados anteriores y Continuar; vacío posterior es consultado sin disponibilidad', async () => {
    const pendiente = diferida(), w = seleccionCompleta(() => pendiente.promise), antes = capturaLocal(w);
    const consulta = w.consultarDisponibilidad();
    assert.equal(w.estadoDisponibilidad, 'loading'); assert.equal(w.puedeElegirProfesional, false);
    assert.deepEqual(w.horariosDisponibles, []); assert.equal(w.estadoFecha('2026-10-17'), 'no_consultada');
    w.seleccionarFecha('2026-10-17'); w.seleccionarInicio(slot().inicio_at); assert.equal(w.inicioSeleccionado, null);
    pendiente.resolve(json(agregado([]))); await consulta;
    assert.equal(w.estadoDisponibilidad, 'empty'); assert.equal(w.estadoFecha('2026-10-17'), 'sin_disponibilidad');
    assert.equal(w.estadoFecha('2026-10-24'), 'no_consultada'); assert.deepEqual(capturaLocal(w), antes);
});

test('Paso 1: retry mantiene ventana, File, descripción y UUID; error no declara fechas sin disponibilidad', async () => {
    const urls = [];
    const w = preparar(async (url) => { urls.push(url); if (urls.length === 1) throw new TypeError('red privada'); return json(agregado()); });
    const antes = capturaLocal(w), archivo = w.archivos[0].archivo;
    await w.consultarDisponibilidad(); assert.equal(w.estadoDisponibilidad, 'error');
    assert.equal(w.estadoFecha('2026-10-17'), 'no_consultada'); assert.equal(w.ventanaConsultada, null);
    await w.consultarDisponibilidad(); assert.equal(urls[0], urls[1]); assert.equal(w.estadoDisponibilidad, 'success');
    assert.deepEqual(capturaLocal(w), antes); assert.equal(w.archivos[0].archivo, archivo);
});
