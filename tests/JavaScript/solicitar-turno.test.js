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
function preparar(fetchImpl = () => assert.fail('Consulta no esperada'), config = configuracion) {
    const w = solicitarTurno(config, { fetchImpl });
    w.$refs = new Proxy({}, { get: () => ({ focus() {} }) });
    // Los tests unitarios de estado consultan explícitamente; el ciclo inicial completo se prueba aparte.
    w.$nextTick = () => {}; w.$watch = () => {};
    w.motivo = 'Motivo conservado';
    w.archivos = [{ id: 100, archivo: new File(['%PDF'], 'estudio.pdf', { type: 'application/pdf' }), descripcion: 'Descripción conservada', error: '' }];
    w.init(); w.$nextTick = (fn) => fn(); return w;
}
function conInicio(fetchImpl, config) {
    const w = preparar(fetchImpl, config);
    w.diasDisponibles = agregado([slot().inicio_at, '2026-10-17 10:30:00', '2026-10-18 10:00:00']).dias;
    w.ventanaConsultada = { ...ventanaInicial }; w.estadoDisponibilidad = 'success';
    w.seleccionarFecha('2026-10-17'); w.seleccionarInicio(slot().inicio_at); return w;
}
function seleccionCompleta(fetchImpl, config) {
    const w = conInicio(fetchImpl, config);
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
function enPaso4(fetchImpl, config) {
    const w = seleccionCompleta(fetchImpl, config);
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
    assert.deepEqual([...datos.keys()], ['clave_idempotencia', 'motivo_solicitud', 'kinesiologa_id', 'inicio_at',
        'adjuntos[0][archivo]', 'adjuntos[0][descripcion]', 'adjuntos[1][archivo]', 'adjuntos[1][descripcion]',
        'adjuntos[2][archivo]', 'adjuntos[2][descripcion]']);
    assert.equal(datos.get('clave_idempotencia'), configuracion.uuid);
    assert.equal(datos.get('motivo_solicitud'), w.motivo);
    assert.equal(datos.get('kinesiologa_id'), '1'); assert.equal(datos.get('inicio_at'), slot().inicio_at);
    for (const [indice, entrada] of w.archivos.entries()) {
        assert.equal(datos.get(`adjuntos[${indice}][archivo]`), [files[0], files[2], files[3]][indice]);
        assert.equal(datos.get(`adjuntos[${indice}][descripcion]`), entrada.descripcion);
    }
    for (const campo of ['adjuntos[]', 'fin_at', 'fecha', 'duracion', 'paciente_id', 'user_id', 'tipo', 'estado', 'profesionales', 'es_replay', 'ruta_storage', 'mime_type']) assert.equal(datos.has(campo), false);
});

for (const replay of [false, true]) test(`éxito ${replay ? '200 replay' : '201 nuevo'}: sin adjuntos, un feedback normal, sin beforeunload y redirect configurado`, async () => {
    const { avisos, feedback, destinos } = entornoConfirmacion();
    let posts = 0;
    const w = enPaso4(async (url, opciones) => { posts++; assert.equal([...opciones.body.keys()].some((clave) => clave.startsWith('adjuntos')), false); return exito(replay); });
    w.archivos = [];
    assert.equal(avisos.size, 1);
    await w.confirmarTurno(); await w.confirmarTurno();
    assert.equal(w.consolidado, true); assert.equal(w.puedeConfirmar, false); assert.equal(posts, 1);
    assert.equal(avisos.size, 0); w.actualizarAvisoAbandono(); assert.equal(avisos.size, 0);
    assert.deepEqual(destinos, [configuracion.urlMisTurnos]);
    assert.equal(feedback.length, 1); assert.equal(feedback[0].icon, 'success');
    assert.equal(feedback[0].title, 'Turno registrado correctamente'); assert.equal(feedback[0].confirmButtonText, 'Entendido');
    assert.match(feedback[0].html, /Primera consulta.*Profesional A.*17.*octubre.*2026.*10:00 a 10:30.*Pendiente/);
    assert.match(feedback[0].html, /El turno ya fue registrado y ocupa la agenda de la profesional\./);
    assert.doesNotMatch(feedback[0].html, /Confirmado|duplicado|replay|email|SMS|WhatsApp|push/);
    assert.equal(feedback[0].showCloseButton, true);
    assert.equal(feedback[0].closeButtonAriaLabel, 'Entendido, ir a Mis turnos');
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
    let posts = 0;
    const w = enPaso4(async (url, opciones) => {
        assert.equal(opciones.method, 'POST'); posts++;
        return json({ code: 'primera_consulta_activa', message: 'No comparar' }, 409);
    });
    const foco = observarFoco(w), local = capturaLocal(w);
    const antes = captura(w); await w.confirmarTurno(); await w.confirmarTurno();
    foco.flush(); assert.deepEqual(foco.focos, ['bloqueoPrimeraConsulta']);
    assert.deepEqual(captura(w), antes); assert.deepEqual(capturaLocal(w), local);
    assert.equal(w.primeraConsultaActiva, true); assert.equal(w.bloqueado, true); assert.equal(w.puedeConfirmar, false);
    assert.equal(w.resultadoIncierto, false); assert.equal(w.recuperacion, null); assert.equal(w.tipoRecuperacion, null);
    assert.equal(w.paso, 4); assert.deepEqual(feedback, []); assert.deepEqual(destinos, []);
    w.anterior(); await w.siguiente(); await w.consultarDisponibilidad(); await w.cargarProfesionalesDisponibles();
    w.salirPorPrimeraConsultaActiva(); w.salirPorPrimeraConsultaActiva(); await w.confirmarTurno();
    assert.equal(posts, 1);
    assert.deepEqual(destinos, [configuracion.urlMisTurnos]); assert.equal(avisos.size, 0); assert.equal(w.consolidado, false);
});

for (const replay of [false, true]) for (const cierre of ['Entendido', 'X']) {
    test(`éxito ${replay ? 'replay' : 'nuevo'} espera ${cierre}, mantiene formulario terminal y navega una sola vez`, async () => {
        const { feedback, destinos, avisos } = entornoConfirmacion(), modal = diferida(); let posts = 0;
        window.Swal.fire = (opciones) => { feedback.push(opciones); return modal.promise; };
        const destino = '/destino-configurado-745?origen=wizard';
        const w = enPaso4(async () => { posts++; return exito(replay); }, { ...configuracion, urlMisTurnos: destino });
        const antes = capturaLocal(w), envio = w.confirmarTurno();
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(w.consolidado, true); assert.equal(w.puedeConfirmar, false); assert.deepEqual(destinos, []);
        assert.equal(avisos.size, 0); assert.equal(feedback.length, 1);
        await w.confirmarTurno(); await w.finalizarConfirmacion(); await w.volverAMisTurnos();
        w.anterior(); await w.siguiente(); w.quitarArchivo(100); w.seleccionarProfesional(2);
        assert.equal(posts, 1); assert.equal(feedback.length, 1); assert.deepEqual(capturaLocal(w), antes);
        modal.resolve(cierre === 'X' ? { isDismissed: true, dismiss: 'close' } : { isConfirmed: true });
        await envio; await w.confirmarTurno(); await w.finalizarConfirmacion();
        assert.deepEqual(destinos, [destino]); assert.equal(posts, 1); assert.equal(feedback.length, 1);
        assert.equal(w.resultadoIncierto, false); assert.equal(w.recuperacion, null);
    });
}

test('D-01 usa el destino configurado y no permite salida terminal sin respuesta D-01', async () => {
    const { destinos } = entornoConfirmacion(), destino = '/mis-turnos-configurado?origen=d01';
    const w = enPaso4(async () => json({ code: 'primera_consulta_activa' }, 409), { ...configuracion, urlMisTurnos: destino });
    w.salirPorPrimeraConsultaActiva(); assert.deepEqual(destinos, []);
    await w.confirmarTurno(); w.salirPorPrimeraConsultaActiva(); assert.deepEqual(destinos, [destino]);
});

test('éxito escapa el nombre público al componer el HTML de SweetAlert', async () => {
    const { feedback } = entornoConfirmacion(), w = enPaso4(async () => exito());
    w.profesionales[0].nombre = '<img src=x onerror="alert(1)"> & \'Profesional\'';
    await w.confirmarTurno();
    assert.match(feedback[0].html, /&lt;img src=x onerror=&quot;alert\(1\)&quot;&gt; &amp; &#39;Profesional&#39;/);
    assert.doesNotMatch(feedback[0].html, /<img|<script/);
});

for (const [status, replay] of [[201, true], [200, false]]) test(`éxito inconsistente ${status}/${replay} conserva incertidumbre sin modal ni redirect`, async () => {
    const { feedback, destinos } = entornoConfirmacion();
    const w = enPaso4(async () => json({ turno_id: 42, es_replay: replay, redirect_url: '/ruta-response' }, status));
    const antes = capturaLocal(w); await w.confirmarTurno();
    assert.equal(w.consolidado, false); assert.equal(w.resultadoIncierto, true); assert.equal(w.puedeConfirmar, true);
    assert.deepEqual(feedback, []); assert.deepEqual(destinos, []); assert.deepEqual(capturaLocal(w), antes);
});

test('409 profesional recupera R1 con lista fresca y exige selección explícita', async () => {
    const { destinos } = entornoConfirmacion();
    const w = enPaso4(async (url, opciones) => opciones.method === 'POST'
        ? json({ code: 'profesional_no_elegible', message: 'No comparar' }, 409) : json(profesionales()));
    const file = w.archivos[0].archivo;
    await w.confirmarTurno();
    assert.equal(w.paso, 2); assert.equal(w.kinesiologaId, null); assert.equal(w.intervaloSeleccionado, null);
    assert.deepEqual(w.profesionales, profesionales().profesionales); assert.equal(w.archivos[0].archivo, file);
    assert.equal(w.claveIdempotencia, configuracion.uuid); assert.equal(w.motivo, 'Motivo conservado');
    assert.equal(w.tipoRecuperacion, 'R1'); assert.equal(w.puedeConfirmar, false);
    w.seleccionarProfesional(2); assert.equal(w.kinesiologaId, 2); assert.deepEqual(destinos, []);
    assert.equal(w.tipoRecuperacion, null); assert.equal(w.mensajeConfirmacion, '');
    assert.equal(w.inicioSeleccionado, slot().inicio_at);
});

test('409 slot retira el inicio y su profesional, conserva captura y vuelve al Paso 1', async () => {
    entornoConfirmacion(); let gets = 0;
    const w = enPaso4(async (url, opciones) => {
        if (opciones.method === 'POST') return json({ code: 'slot_no_disponible', message: 'Irrelevante' }, 409);
        gets++; return json(gets === 1 ? { inicio_at: slot().inicio_at, profesionales: [] } : agregado(['2026-10-17 11:00:00']));
    });
    const antes = captura(w); await w.confirmarTurno();
    assert.equal(gets, 2); assert.equal(w.paso, 1); assert.equal(w.intervaloSeleccionado, null);
    assert.deepEqual(captura(w), { ...antes, slot: null, profesional: null }); assert.equal(w.estadoDisponibilidad, 'success');
    assert.equal(w.mensajeConfirmacion, 'El horario que habías seleccionado ya no está disponible.');
    assert.equal(w.enviando, false);
});

for (const code of ['profesional_no_elegible', 'slot_no_disponible']) {
    for (const resultado of ['R1', 'R2', 'R3', 'vacía']) {
        test(`${code}: clasificación ${resultado} por GET frescos, conserva snapshot y no autoselecciona`, async () => {
            const { feedback, destinos } = entornoConfirmacion(), llamadas = [];
            const otroInicio = resultado === 'R2' ? '2026-10-17 11:00:00' : '2026-10-18 10:00:00';
            const lista = profesionales(); lista.profesionales = [lista.profesionales[1]];
            const w = enPaso4(async (url, opciones) => {
                llamadas.push({ url: new URL(url), opciones });
                if (opciones.method === 'POST') return json({ code, message: 'Causa interna que no debe mostrarse' }, 409);
                if (new URL(url).pathname.endsWith('profesionales-disponibles')) {
                    return json(resultado === 'R1' ? lista : { inicio_at: slot().inicio_at, profesionales: [] });
                }
                return json(agregado(resultado === 'vacía' ? [] : [otroInicio]));
            });
            const antes = capturaLocal(w), file = w.archivos[0].archivo;
            await w.confirmarTurno();
            assert.deepEqual(capturaLocal(w), antes); assert.equal(w.archivos[0].archivo, file);
            assert.equal(llamadas[0].opciones.method, 'POST');
            assert.deepEqual(Object.fromEntries(llamadas[1].url.searchParams), { inicio_at: slot().inicio_at });
            assert.equal(llamadas[1].url.pathname, '/turnos/solicitar/profesionales-disponibles');
            assert.equal(llamadas.length, resultado === 'R1' ? 2 : 3);
            if (resultado !== 'R1') {
                assert.equal(llamadas[2].url.pathname, '/turnos/solicitar/disponibilidad');
                assert.deepEqual(Object.fromEntries(llamadas[2].url.searchParams), ventanaInicial);
            }
            for (const { opciones } of llamadas.slice(1)) {
                assert.equal(opciones.method ?? 'GET', 'GET'); assert.equal(opciones.body, undefined);
                assert.equal(opciones.credentials, 'same-origin'); assert.equal(opciones.cache, 'no-store');
            }
            assert.equal(w.tipoRecuperacion, resultado === 'vacía' ? 'R3' : resultado);
            assert.equal(w.paso, resultado === 'R1' ? 2 : 1); assert.equal(w.kinesiologaId, null);
            assert.equal(w.fechaSeleccionada, ['R1', 'R2'].includes(resultado) ? '2026-10-17' : null);
            assert.equal(w.inicioSeleccionado, resultado === 'R1' ? slot().inicio_at : null);
            assert.deepEqual(w.profesionales, resultado === 'R1' ? lista.profesionales : []);
            if (resultado !== 'R1') {
                assert.deepEqual(w.diasDisponibles, agregado(resultado === 'vacía' ? [] : [otroInicio]).dias);
                assert.equal(w.estadoDisponibilidad, resultado === 'vacía' ? 'empty' : 'success');
                assert.deepEqual(w.ventanaConsultada, ventanaInicial);
            }
            assert.equal(w.recuperacion, null); assert.equal(w.resultadoIncierto, false); assert.equal(w.puedeConfirmar, false);
            assert.doesNotMatch(w.mensajeConfirmacion, /Causa interna/);
            assert.ok(w.detalleRecuperacion.length); assert.deepEqual(feedback, []); assert.deepEqual(destinos, []);
        });
    }
}

for (const fase of ['profesionales', 'disponibilidad']) {
    for (const fallo of ['red', '500', '422', 'JSON', 'contrato']) {
        test(`recovery ${fase} ${fallo}: error técnico conserva contexto y retry repite sólo GET fallido`, async () => {
            const { feedback, destinos } = entornoConfirmacion(), llamadas = [];
            let fallar = true;
            const w = enPaso4(async (url, opciones) => {
                llamadas.push({ url: new URL(url), opciones });
                if (opciones.method === 'POST') return json({ code: 'slot_no_disponible' }, 409);
                const esProfesionales = new URL(url).pathname.endsWith('profesionales-disponibles');
                if (fallar && esProfesionales === (fase === 'profesionales')) {
                    if (fallo === 'red') throw new TypeError('Red');
                    if (fallo === 'JSON') return new Response('{', { status: 200 });
                    return json({}, fallo === 'contrato' ? 200 : Number(fallo));
                }
                return json(esProfesionales ? (fase === 'profesionales' ? profesionales() : { inicio_at: slot().inicio_at, profesionales: [] })
                    : agregado(['2026-10-17 11:00:00']));
            });
            const antes = capturaLocal(w), f = observarFoco(w);
            await w.confirmarTurno(); f.flush();
            assert.equal(w.recuperacion.estado, 'error'); assert.equal(w.recuperacion.fase, fase);
            assert.equal(w.recuperacion.kinesiologa, 1); assert.equal(w.recuperacion.inicio, slot().inicio_at);
            assert.equal(w.fechaSeleccionada, '2026-10-17'); assert.equal(w.inicioSeleccionado, slot().inicio_at);
            assert.equal(w.kinesiologaId, null); assert.deepEqual(w.profesionales, []);
            assert.deepEqual(capturaLocal(w), antes); assert.equal(w.resultadoIncierto, false);
            assert.equal(w.tipoRecuperacion, null); assert.equal(w.bloqueado, true); assert.equal(w.puedeConfirmar, false);
            assert.match(w.mensajeConfirmacion, /No se registró el turno/); assert.equal(f.focos.at(-1), 'mensajeConfirmacion');
            const cantidad = llamadas.length;
            await new Promise((resolve) => setImmediate(resolve));
            assert.equal(llamadas.length, cantidad); // Sin acción del usuario no se inicia otro request.
            await w.confirmarTurno(); await w.consultarDisponibilidad(); await w.cargarProfesionalesDisponibles();
            w.seleccionarInicio('2026-10-17 10:30:00'); w.seleccionarProfesional(2); w.anterior();
            assert.equal(llamadas.length, cantidad); assert.equal(w.inicioSeleccionado, slot().inicio_at);
            fallar = false; await w.reintentarRecuperacion(); f.flush();
            assert.equal(llamadas.length, cantidad + 1);
            assert.equal(llamadas.at(-1).url.pathname.endsWith('profesionales-disponibles'), fase === 'profesionales');
            assert.equal(llamadas.filter(({ opciones }) => opciones.method === 'POST').length, 1);
            assert.equal(w.recuperacion, null); assert.equal(w.tipoRecuperacion, fase === 'profesionales' ? 'R1' : 'R2');
            assert.deepEqual(capturaLocal(w), antes); assert.deepEqual(feedback, []); assert.deepEqual(destinos, []);
        });
    }
}

test('recovery pendiente bloquea doble click, navegación y consultas normales sin perder snapshot', async () => {
    entornoConfirmacion(); const pendiente = diferida(); let llamadas = 0;
    const w = enPaso4(async (url, opciones) => {
        llamadas++; return opciones.method === 'POST' ? json({ code: 'slot_no_disponible' }, 409) : pendiente.promise;
    });
    const antes = capturaLocal(w), envio = w.confirmarTurno();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(w.recuperando, true); assert.equal(w.enviando, true); assert.equal(w.paso, 2);
    assert.equal(w.recuperacion.kinesiologa, 1); assert.deepEqual(w.recuperacion.ventana, ventanaInicial);
    await w.confirmarTurno(); await w.reintentarRecuperacion(); await w.volverAMisTurnos();
    await w.consultarDisponibilidad(); await w.cargarProfesionalesDisponibles(); w.anterior();
    assert.equal(llamadas, 2); assert.deepEqual(capturaLocal(w), antes);
    pendiente.resolve(json(profesionales())); await envio; assert.equal(w.tipoRecuperacion, 'R1');
});

test('R1 conserva incluso la misma profesional reaparecida sin elegirla automáticamente', async () => {
    entornoConfirmacion(); const lista = profesionales(); lista.profesionales = [lista.profesionales[0]];
    const w = enPaso4(async (url, opciones) => opciones.method === 'POST'
        ? json({ code: 'profesional_no_elegible' }, 409) : json(lista));
    await w.confirmarTurno(); assert.equal(w.tipoRecuperacion, 'R1'); assert.equal(w.kinesiologaId, null);
    assert.deepEqual(w.profesionales, lista.profesionales); w.seleccionarProfesional(1);
    assert.equal(w.kinesiologaId, 1);
});

for (const quedanOtrosHorarios of [true, false]) test(`inicio alcanzado según backend: ${quedanOtrosHorarios ? 'R2' : 'R3'}, sin reloj cliente`, async () => {
    entornoConfirmacion();
    const w = enPaso4(async (url, opciones) => {
        if (opciones.method === 'POST') return json({ code: 'slot_no_disponible' }, 409);
        if (new URL(url).pathname.endsWith('profesionales-disponibles')) return json({ inicio_at: slot().inicio_at, profesionales: [] });
        return json(agregado(quedanOtrosHorarios ? ['2026-10-17 11:00:00'] : []));
    });
    await w.confirmarTurno(); assert.equal(w.tipoRecuperacion, quedanOtrosHorarios ? 'R2' : 'R3');
    assert.equal(w.inicioSeleccionado, null); assert.equal(w.fechaSeleccionada, quedanOtrosHorarios ? '2026-10-17' : null);
});

test('error técnico de recovery permite abandonar con el aviso existente, sin nuevos GET ni POST', async () => {
    const { destinos } = entornoConfirmacion(); let llamadas = 0;
    const w = enPaso4(async (url, opciones) => {
        llamadas++; if (opciones.method === 'POST') return json({ code: 'slot_no_disponible' }, 409);
        throw new TypeError('Red');
    });
    await w.confirmarTurno(); await w.volverAMisTurnos();
    assert.equal(llamadas, 2); assert.deepEqual(destinos, [configuracion.urlMisTurnos]);
});

for (const fase of ['profesionales', 'disponibilidad']) test(`destroy durante recovery ${fase} aborta e ignora respuesta y JSON tardío`, async () => {
    for (const tardio of ['respuesta', 'JSON']) {
        entornoConfirmacion(); const pendiente = diferida(); let signal;
        const w = enPaso4(async (url, opciones) => {
            if (opciones.method === 'POST') return json({ code: 'slot_no_disponible' }, 409);
            if (fase === 'disponibilidad' && new URL(url).pathname.endsWith('profesionales-disponibles')) {
                return json({ inicio_at: slot().inicio_at, profesionales: [] });
            }
            signal = opciones.signal;
            return tardio === 'respuesta' ? pendiente.promise : { status: 200, json: () => pendiente.promise };
        });
        const envio = w.confirmarTurno(); await new Promise((resolve) => setImmediate(resolve));
        const antes = JSON.stringify(w);
        w.destroy(); assert.equal(signal.aborted, true);
        const body = fase === 'profesionales' ? profesionales() : agregado([]);
        pendiente.resolve(tardio === 'respuesta' ? json(body) : body); await envio;
        assert.equal(JSON.stringify(w), antes);
    }
});

test('una recuperación nueva descarta respuesta tardía de la anterior, aunque fetch ignore abort', async () => {
    entornoConfirmacion(); const pendiente = diferida(); let gets = 0, signal;
    const w = enPaso4(async (url, opciones) => {
        if (opciones.method === 'POST') return json({ code: 'slot_no_disponible' }, 409);
        gets++; if (gets === 1) { signal = opciones.signal; return pendiente.promise; }
        return json(profesionales('2026-10-18 10:00:00'));
    });
    const envio = w.confirmarTurno(); await new Promise((resolve) => setImmediate(resolve));
    await w.iniciarRecuperacion({ fecha: '2026-10-18', inicio: '2026-10-18 10:00:00', kinesiologa: 2, ventana: ventanaInicial });
    assert.equal(signal.aborted, true);
    const estadoSinEnvio = () => {
        const { enviando, bloqueado, ...estado } = JSON.parse(JSON.stringify(w));
        return estado;
    };
    const antes = estadoSinEnvio();
    pendiente.resolve(json({ inicio_at: slot().inicio_at, profesionales: [] })); await envio;
    // Sólo finaliza el POST original; la selección/lista del nuevo contexto no se reemplaza.
    assert.equal(w.inicioSeleccionado, '2026-10-18 10:00:00'); assert.equal(w.tipoRecuperacion, 'R1');
    assert.deepEqual(w.profesionales, profesionales('2026-10-18 10:00:00').profesionales);
    assert.deepEqual(estadoSinEnvio(), antes); assert.equal(w.enviando, false); assert.equal(gets, 2);
});

test('proyecciones cambiantes no clasifican falsamente R2 ni generan consultas automáticas', async () => {
    entornoConfirmacion(); let llamadas = 0;
    const w = enPaso4(async (url, opciones) => {
        llamadas++;
        if (opciones.method === 'POST') return json({ code: 'slot_no_disponible' }, 409);
        return json(new URL(url).pathname.endsWith('profesionales-disponibles')
            ? { inicio_at: slot().inicio_at, profesionales: [] } : agregado());
    });
    await w.confirmarTurno(); assert.equal(llamadas, 3); assert.equal(w.tipoRecuperacion, null);
    assert.equal(w.recuperacion.estado, 'error'); assert.equal(w.recuperacion.fase, 'profesionales');
    assert.equal(w.inicioSeleccionado, slot().inicio_at); assert.equal(w.resultadoIncierto, false);
});

for (const resultado of ['R1', 'R2', 'R3']) test(`${resultado}: selección posterior y nuevo POST conservan UUID, File y descripción`, async () => {
    entornoConfirmacion(); const envios = [];
    const nuevoInicio = resultado === 'R3' ? '2026-10-18 10:00:00' : '2026-10-17 10:15:00';
    const w = enPaso4(async (url, opciones) => {
        if (opciones.method === 'POST') {
            envios.push(opciones.body); return envios.length === 1 ? json({ code: 'slot_no_disponible' }, 409) : exito();
        }
        if (new URL(url).pathname.endsWith('profesionales-disponibles')) {
            const inicio = new URL(url).searchParams.get('inicio_at');
            return json(resultado === 'R1' || inicio === nuevoInicio ? profesionales(inicio) : { inicio_at: inicio, profesionales: [] });
        }
        return json(agregado([nuevoInicio]));
    });
    const antes = capturaLocal(w); await w.confirmarTurno();
    if (resultado !== 'R1') {
        if (resultado === 'R3') w.seleccionarFecha('2026-10-18');
        w.seleccionarInicio(nuevoInicio); await w.siguiente();
    }
    assert.equal(w.kinesiologaId, null); w.seleccionarProfesional(2); await w.siguiente(); await w.siguiente();
    assert.equal(w.paso, 4); assert.equal(w.mensajeConfirmacion, '');
    await w.confirmarTurno(); assert.equal(envios.length, 2);
    for (const datos of envios) {
        assert.equal(datos.get('clave_idempotencia'), antes.uuid);
        assert.equal(datos.get('motivo_solicitud'), antes.motivo);
        assert.equal(datos.get('adjuntos[0][archivo]'), antes.archivos[0].archivo);
        assert.equal(datos.get('adjuntos[0][descripcion]'), antes.archivos[0].descripcion);
    }
    assert.equal(envios[1].get('kinesiologa_id'), '2');
    assert.equal(envios[1].get('inicio_at'), resultado === 'R1' ? slot().inicio_at : nuevoInicio);
});

test('recovery usa ventana original; fallback contiene fecha sin imponer horizonte ni reloj local', async () => {
    const w = preparar(async (url) => {
        const parsed = new URL(url);
        if (parsed.pathname.endsWith('profesionales-disponibles')) return json({ inicio_at: '2030-12-31 10:00:00', profesionales: [] });
        assert.deepEqual(Object.fromEntries(parsed.searchParams), { desde: '2030-12-31', hasta: '2031-01-06' });
        return json(agregado([], { desde: '2030-12-31', hasta: '2031-01-06' }));
    });
    await w.iniciarRecuperacion({ fecha: '2030-12-31', inicio: '2030-12-31 10:00:00', kinesiologa: 1, ventana: ventanaInicial });
    assert.equal(w.tipoRecuperacion, 'R3'); assert.equal(w.fechaSeleccionada, null);
    assert.deepEqual(w.ventana, { desde: '2030-12-31', hasta: '2031-01-06' });
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
        assert.equal(datos.get('motivo_solicitud'), antes.motivo);
        assert.equal(datos.get('inicio_at'), antes.slot.inicio_at);
        assert.equal(datos.get('kinesiologa_id'), String(antes.profesional));
        assert.equal([...datos.keys()].length, 4 + w.archivos.length * 2);
        for (const [indice, entrada] of w.archivos.entries()) {
            assert.equal(datos.get(`adjuntos[${indice}][archivo]`), antes.archivos[indice]);
            assert.equal(datos.get(`adjuntos[${indice}][descripcion]`), entrada.descripcion);
        }
        assert.equal(datos.has('adjuntos[]'), false);
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

test('error de descripción local lleva foco a su input tras renderizar, sin alterar la selección', async () => {
    const w = seleccionCompleta(), f = observarFoco(w);
    w.paso = 3; w.archivos[0].descripcion = '';
    const antes = capturaLocal(w);
    w.$root = { querySelector: (selector) => {
        assert.equal(selector, '[id^="descripcion-adjunto-"][aria-invalid="true"]');
        return { getClientRects: () => [{}], focus: () => f.focos.push('descripcion-adjunto-100') };
    } };
    await w.siguiente();
    assert.deepEqual(f.focos, []);
    f.flush();
    assert.deepEqual(f.focos, ['descripcion-adjunto-100']);
    assert.equal(w.paso, 3); assert.equal(w.claveIdempotencia, antes.uuid);
    assert.equal(w.archivos[0].archivo, antes.archivos[0].archivo);
    assert.equal(w.archivos[0].descripcionRevisada, true);
});

test('422 de descripción enfoca el campo conservado, con fallback al resumen si está oculto', async () => {
    for (const visible of [true, false]) {
        entornoConfirmacion();
        const w = enPaso4(async () => json({ errors: { 'adjuntos.0.descripcion': ['Revisá la descripción'] } }, 422));
        const f = observarFoco(w);
        w.$root = { querySelector: () => ({ getClientRects: () => visible ? [{}] : [], focus: () => f.focos.push('descripcion') }) };
        await w.confirmarTurno(); f.flush();
        assert.equal(w.paso, 3);
        assert.equal(f.focos.at(-1), visible ? 'descripcion' : 'documentacion');
        assert.equal(w.resultadoIncierto, false);
    }
});

for (const [errores, destino] of [[{ motivo_solicitud: ['Corregí el motivo'] }, 'motivo'], [{ 'adjuntos.0.archivo': ['Archivo inválido'] }, 'documentacion']]) {
    test(`422 solicita foco en ${destino} al recuperar Paso 3`, async () => {
        entornoConfirmacion();
        const w = enPaso4(async () => json({ errors: errores }, 422)), f = observarFoco(w);
        await w.confirmarTurno(); f.flush(); assert.equal(w.paso, 3); assert.equal(f.focos.at(-1), destino);
    });
}

for (const code of ['profesional_no_elegible', 'slot_no_disponible']) {
    test(`409 ${code}: foco contextual; refresh exitoso no lo roba`, async () => {
        entornoConfirmacion();
        const w = enPaso4(async (url, opciones) => opciones.method === 'POST' ? json({ code }, 409) : json(profesionales()));
        const f = observarFoco(w); await w.confirmarTurno(); f.flush(); assert.deepEqual(f.focos, ['mensajeConfirmacion']);
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

test('Paso 2: fallback local usa sólo nombre público y duración se deriva del intervalo civil propio', () => {
    const w = preparar();
    for (const [nombre, iniciales] of [['Ana Gómez', 'AG'], ['  María   del Carmen ', 'MD'], ['Érica', 'É'], ['李 明', '李明']]) {
        assert.equal(w.inicialesProfesional(nombre), iniciales);
    }
    assert.equal(w.duracionLegible('2026-10-17 10:00:00', '2026-10-17 10:30:00'), '30 min');
    assert.equal(w.duracionLegible('2026-10-17 10:00:00', '2026-10-17 10:45:00'), '45 min');
    assert.equal(w.duracionLegible('2026-12-31 23:45:00', '2027-01-01 00:30:00'), '45 min');
    assert.equal(w.duracionLegible('2028-02-28 23:45:37', '2028-02-29 00:15:37'), '30 min');
});

test('Paso 2: lista conserva orden backend, mismo inicio y finales propios; sólo elección explícita permite continuar', async () => {
    const inicio = '2026-10-17 10:00:00';
    const lista = [
        { kinesiologa_id: 72, nombre: 'Zeta Prueba', fin_at: '2026-10-17 10:30:00' },
        { kinesiologa_id: 19, nombre: 'Alfa Prueba', fin_at: '2026-10-17 10:45:00' },
    ];
    const w = conInicio(async () => json({ inicio_at: inicio, profesionales: lista }));
    const antes = capturaLocal(w);
    await w.siguiente(); assert.equal(w.paso, 2); assert.deepEqual(w.profesionales, lista);
    assert.equal(w.profesionalSeleccionada, null); assert.equal(w.intervaloSeleccionado, null);
    await w.siguiente(); assert.equal(w.paso, 2);
    assert.deepEqual(w.profesionales.map((p) => [w.horaLegible(inicio), w.horaLegible(p.fin_at), w.duracionLegible(inicio, p.fin_at)]),
        [['10:00', '10:30', '30 min'], ['10:00', '10:45', '45 min']]);
    w.seleccionarProfesional(0); w.seleccionarProfesional('Alfa Prueba'); assert.equal(w.profesionalSeleccionada, null);
    w.seleccionarProfesional(72); assert.deepEqual(w.intervaloSeleccionado, { inicio_at: inicio, fin_at: lista[0].fin_at });
    w.seleccionarProfesional(19); assert.deepEqual(w.intervaloSeleccionado, { inicio_at: inicio, fin_at: lista[1].fin_at });
    await w.siguiente(); assert.equal(w.paso, 3); assert.deepEqual(capturaLocal(w), antes);
    assert.equal('finSeleccionado' in w, false);
});

test('Paso 2: una sola profesional tampoco se autoselecciona; volver sin cambiar contexto no repite el GET', async () => {
    let consultas = 0;
    const w = conInicio(async () => { consultas++; return json({ ...profesionales(), profesionales: profesionales().profesionales.slice(0, 1) }); });
    const antes = capturaLocal(w);
    await w.siguiente(); assert.equal(consultas, 1); assert.equal(w.profesionales.length, 1);
    assert.equal(w.kinesiologaId, null); await w.siguiente(); assert.equal(w.paso, 2);
    w.seleccionarProfesional(1); const intervalo = w.intervaloSeleccionado;
    w.anterior(); assert.equal(w.paso, 1); assert.deepEqual(w.intervaloSeleccionado, intervalo);
    await w.siguiente(); assert.equal(consultas, 1); assert.equal(w.kinesiologaId, 1);
    assert.deepEqual(w.intervaloSeleccionado, intervalo); assert.deepEqual(capturaLocal(w), antes);
});

test('Paso 2: loading retira opciones anteriores y retry consulta el mismo inicio sin perder captura', async () => {
    const pendiente = diferida(), urls = []; let consultas = 0;
    const w = seleccionCompleta(async (url) => {
        urls.push(url); consultas++;
        if (consultas === 1) return pendiente.promise;
        return json(profesionales());
    });
    w.paso = 2; const antes = capturaLocal(w), inicio = w.inicioSeleccionado, file = w.archivos[0].archivo;
    const carga = w.cargarProfesionalesDisponibles();
    assert.equal(w.estadoProfesionales, 'loading'); assert.deepEqual(w.profesionales, []);
    w.seleccionarProfesional(1); await w.siguiente(); assert.equal(w.paso, 2); assert.equal(w.kinesiologaId, null);
    pendiente.reject(new TypeError('Error privado de conexión')); await carga;
    assert.equal(w.estadoProfesionales, 'error'); assert.doesNotMatch(w.errorConsultaProfesionales, /privado/);
    await w.cargarProfesionalesDisponibles();
    assert.equal(w.estadoProfesionales, 'success'); assert.equal(urls[0], urls[1]);
    assert.deepEqual(Object.fromEntries(new URL(urls[1]).searchParams), { inicio_at: inicio });
    assert.equal(w.inicioSeleccionado, inicio); assert.equal(w.kinesiologaId, null);
    assert.equal(w.archivos[0].archivo, file); assert.deepEqual(capturaLocal(w), antes);
});

test('Paso 2: 200 vacío corresponde al inicio consultado y no permite avanzar ni se confunde con error', async () => {
    const w = conInicio(async () => json({ inicio_at: slot().inicio_at, profesionales: [] })), antes = capturaLocal(w);
    await w.siguiente(); assert.equal(w.paso, 2); assert.equal(w.estadoProfesionales, 'empty');
    assert.equal(w.inicioProfesionales, w.inicioSeleccionado); assert.equal(w.errorConsultaProfesionales, '');
    w.seleccionarProfesional(1); await w.siguiente(); assert.equal(w.paso, 2);
    assert.equal(w.profesionalSeleccionada, null); assert.deepEqual(capturaLocal(w), antes);
});

test('Paso 2: nuevo inicio invalida selección y exige su propio GET sin cambiar UUID ni documentos', async () => {
    const urls = [];
    const w = conInicio(async (url) => {
        urls.push(new URL(url));
        return json({ inicio_at: new URL(url).searchParams.get('inicio_at'), profesionales: [
            { kinesiologa_id: 1, nombre: 'Profesional A', fin_at: '2026-10-17 11:00:00' },
            { kinesiologa_id: 2, nombre: 'Profesional B', fin_at: '2026-10-17 11:15:00' },
        ] });
    });
    const antes = capturaLocal(w);
    await w.siguiente(); w.seleccionarProfesional(1); w.anterior();
    w.seleccionarInicio('2026-10-17 10:30:00');
    assert.equal(w.kinesiologaId, null); assert.equal(w.inicioProfesionales, null); assert.deepEqual(w.profesionales, []);
    assert.equal(w.intervaloSeleccionado, null); assert.equal(w.estadoProfesionales, 'idle');
    await w.siguiente(); assert.equal(urls.length, 2);
    assert.equal(urls[1].searchParams.get('inicio_at'), '2026-10-17 10:30:00');
    assert.equal(w.inicioProfesionales, w.inicioSeleccionado); assert.equal(w.kinesiologaId, null);
    assert.deepEqual(capturaLocal(w), antes);
});

test('Paso 3: motivo válido sin adjuntos permite revisar; motivo vacío o >máximo no', async () => {
    const w = seleccionCompleta(); w.paso = 3; w.archivos = [];
    for (const motivo of [' ', 'x'.repeat(501)]) {
        w.motivo = motivo; assert.equal(w.puedeAvanzarPaso3, false);
        await w.siguiente(); assert.equal(w.paso, 3);
    }
    w.motivo = 'Motivo válido'; w.corregirMotivo();
    assert.equal(w.puedeAvanzarPaso3, true); await w.siguiente(); assert.equal(w.paso, 4);
});

for (const [nombre, descripcion, valida] of [
    ['vacía', '', false], ['espacios', '   ', false], ['mínimo significativo', ' x ', true],
    ['máximo significativo', '  ' + 'x'.repeat(500) + '  ', true], ['superior al máximo', 'x'.repeat(501), false],
    ['Unicode', '🦴'.repeat(500), true],
]) test(`Paso 3: descripción ${nombre} valida por entrada sin recortar texto local`, async () => {
    const w = seleccionCompleta(); w.paso = 3; w.archivos = [];
    const file = new File(['pdf'], 'estudio.pdf', { type: 'application/pdf' });
    const input = { target: { files: [file], value: 'seleccionado' } };
    w.agregarArchivos(input); const entrada = w.archivos[0];
    assert.equal(input.target.value, ''); assert.equal(entrada.archivo, file);
    assert.equal(entrada.descripcion, ''); assert.equal(entrada.descripcionRevisada, false);
    assert.equal(w.puedeAvanzarPaso3, false); assert.match(w.errorDescripcion(entrada), /Ingresá una descripción/);
    w.corregirDescripcion(entrada.id, descripcion);
    assert.equal(entrada.descripcion, descripcion); assert.equal(w.puedeAvanzarPaso3, valida);
    assert.equal(w.erroresDescripciones.length, valida ? 0 : 1);
    w.revisarDescripcion(entrada.id); assert.equal(entrada.descripcionRevisada, true);
    await w.siguiente(); assert.equal(w.paso, valida ? 4 : 3);
    assert.equal(entrada.descripcion, descripcion);
});

test('Paso 3: selección mixta rechaza sólo el inválido; contador y bytes incluyen sólo aceptados y descarte no bloquea', async () => {
    const w = seleccionCompleta(); w.paso = 3; w.archivos = [];
    w.agregarArchivos({ target: { files: [new File(['aa'], 'A.pdf', { type: 'application/pdf' }),
        new File(['muchos bytes rechazados'], 'B.exe', { type: 'application/octet-stream' }),
        new File(['ccc'], 'C.pdf', { type: 'application/pdf' })] } });
    assert.equal(w.archivos.length, 2); assert.equal(w.totalBytes, 5); assert.equal(w.erroresSeleccionArchivos.length, 1);
    const [a, c] = w.archivos; w.corregirDescripcion(a.id, 'Descripción A');
    assert.equal(w.puedeAvanzarPaso3, false); w.corregirDescripcion(c.id, 'Descripción C');
    assert.equal(w.puedeAvanzarPaso3, true); assert.equal(w.erroresSeleccionArchivos.length, 1);
    const antes = capturaLocal(w); w.descartarErrorSeleccion(w.erroresSeleccionArchivos[0].id);
    assert.equal(w.erroresSeleccionArchivos.length, 0); assert.deepEqual(capturaLocal(w), antes);
    await w.siguiente(); assert.equal(w.paso, 4);
});

test('Paso 3: política configurable conserva límites exactos y separa errores individuales de cantidad/total', () => {
    const w = solicitarTurno({ ...configuracion, limites: { ...configuracion.limites, maxArchivos: 1, maxBytesPorArchivo: 4, maxBytesTotal: 6 } });
    w.agregarArchivos({ target: { files: [new File(['1234'], 'limite.pdf', { type: 'application/pdf' }),
        new File(['12345'], 'grande.pdf', { type: 'application/pdf' })] } });
    assert.equal(w.archivos.length, 1); assert.equal(w.totalBytes, 4); assert.equal(w.erroresGeneralesArchivos.length, 0);
    assert.equal(w.erroresSeleccionArchivos.length, 1); assert.match(w.erroresSeleccionArchivos[0].error, /supera/);
    w.agregarArchivos({ target: { files: [new File(['12'], 'otro.pdf', { type: 'application/pdf' })] } });
    assert.equal(w.totalBytes, 6); assert.equal(w.erroresGeneralesArchivos.length, 1);
    w.agregarArchivos({ target: { files: [new File(['1'], 'exceso.pdf', { type: 'application/pdf' })] } });
    assert.equal(w.totalBytes, 7); assert.equal(w.erroresGeneralesArchivos.length, 2);
    w.erroresServidor.adjuntos = 'Error global'; w.quitarArchivo(w.archivos.at(-1).id);
    assert.equal(w.erroresServidor.adjuntos, undefined); assert.equal(w.erroresGeneralesArchivos.length, 1);
    w.quitarArchivo(w.archivos.at(-1).id); assert.equal(w.erroresGeneralesArchivos.length, 0);
});

test('Paso 3: MIME/extensión provienen de política e incluyen JPG/JPEG/PNG sin leer contenidos', () => {
    const w = solicitarTurno({ ...configuracion, limites: { ...configuracion.limites,
        mimePermitidos: ['application/pdf', 'image/jpeg', 'image/png'], extensionesPermitidas: ['pdf', 'jpg', 'jpeg', 'png'] } });
    w.agregarArchivos({ target: { files: [new File(['x'], 'A.JPG', { type: 'image/jpeg' }), new File(['x'], 'B.jpeg', { type: 'image/jpeg' }),
        new File(['x'], 'C.png', { type: 'image/png' }), new File(['x'], 'D.pdf', { type: 'application/pdf' }),
        new File(['x'], 'E.png', { type: 'video/mp4' }), new File(['x'], 'F.exe', { type: 'image/png' })] } });
    assert.deepEqual(w.archivos.map((e) => e.archivo.name), ['A.JPG', 'B.jpeg', 'C.png', 'D.pdf']);
    assert.equal(w.totalBytes, 4); assert.equal(w.erroresSeleccionArchivos.length, 2);
    assert.match(w.erroresSeleccionArchivos[0].error, /Formato no permitido/);
});

test('Paso 3: reemplazo inválido conserva original; válido crea nueva identidad, sin descripción ni errores heredados', () => {
    const w = seleccionCompleta(); const antesTurno = { inicio: w.inicioSeleccionado, fecha: w.fechaSeleccionada,
        profesional: w.kinesiologaId, uuid: w.claveIdempotencia, motivo: w.motivo };
    const a = w.archivos[0]; a.errorServidor = 'Error archivo'; a.errorDescripcionServidor = 'Error descripción';
    w.reemplazarArchivo(a.id, new File(['x'], 'invalido.exe'));
    assert.equal(w.archivos[0], a); assert.equal(a.descripcion, 'Descripción conservada');
    assert.equal(w.erroresSeleccionArchivos.length, 1);
    w.reemplazarArchivo(a.id, new File(['nuevo'], 'B.pdf', { type: 'application/pdf' }));
    const b = w.archivos[0]; assert.notEqual(b.id, a.id); assert.equal(b.descripcion, ''); assert.equal(b.descripcionRevisada, false);
    assert.equal(b.errorServidor, undefined); assert.equal(b.errorDescripcionServidor, undefined);
    assert.equal(w.erroresSeleccionArchivos.length, 0); assert.equal(w.puedeAvanzarPaso3, false);
    w.corregirDescripcion(b.id, 'Nueva descripción'); assert.equal(w.puedeAvanzarPaso3, true);
    w.quitarArchivo(b.id); assert.equal(w.archivos.length, 0); assert.equal(w.totalBytes, 0);
    w.agregarArchivos({ target: { files: [new File(['nuevo'], 'B.pdf', { type: 'application/pdf' })] } });
    assert.notEqual(w.archivos[0].id, b.id); assert.equal(w.archivos[0].descripcion, '');
    assert.deepEqual({ inicio: w.inicioSeleccionado, fecha: w.fechaSeleccionada, profesional: w.kinesiologaId,
        uuid: w.claveIdempotencia, motivo: w.motivo }, antesTurno);
});

test('Paso 3: errores servidor de descripción siguen por ID; corregir una no altera otra ni envía requests', async () => {
    const w = seleccionCompleta(); w.paso = 3; w.archivos = [];
    w.agregarArchivos({ target: { files: [new File(['a'], 'A.pdf', { type: 'application/pdf' }), new File(['b'], 'B.pdf', { type: 'application/pdf' })] } });
    const [a, b] = w.archivos; w.corregirDescripcion(a.id, 'A'); w.corregirDescripcion(b.id, 'B');
    const snapshot = [...w.archivos];
    w.aplicarErroresServidor({ 'adjuntos.0.descripcion': ['Error A'], 'adjuntos.1.descripcion': ['Error B'] }, snapshot);
    assert.equal(w.puedeAvanzarPaso3, false); w.corregirDescripcion(a.id, 'A corregida');
    assert.equal(a.errorDescripcionServidor, undefined); assert.equal(b.errorDescripcionServidor, 'Error B');
    w.corregirDescripcion(b.id, 'B corregida'); assert.equal(w.puedeAvanzarPaso3, true);
    await w.siguiente(); assert.equal(w.paso, 4);
});

for (const campo of ['archivo', 'descripcion']) test(`V2: dos pares exactos y 422 ${campo} usan snapshot tras quitar B, con nombres duplicados y array posterior distinto`, async () => {
    entornoConfirmacion(); const pendiente = diferida(); let datos;
    const w = enPaso4(async (url, opciones) => { datos = opciones.body; return pendiente.promise; });
    const a = new File(['A'], 'estudio.pdf', { type: 'application/pdf' });
    const c = new File(['C'], 'estudio.pdf', { type: 'application/pdf' });
    w.archivos = [
        { id: 10, archivo: a, descripcion: ' Descripción A\noriginal ', error: '' },
        { id: 11, archivo: new File(['B'], 'retirado.pdf', { type: 'application/pdf' }), descripcion: 'Descripción B', error: '' },
        { id: 12, archivo: c, descripcion: 'Descripción C', error: '' },
    ];
    w.quitarArchivo(11); const originalC = w.archivos[1];
    const envio = w.confirmarTurno();
    assert.deepEqual([...datos.keys()], ['clave_idempotencia', 'motivo_solicitud', 'kinesiologa_id', 'inicio_at',
        'adjuntos[0][archivo]', 'adjuntos[0][descripcion]', 'adjuntos[1][archivo]', 'adjuntos[1][descripcion]']);
    assert.equal(datos.get('adjuntos[0][archivo]'), a); assert.equal(datos.get('adjuntos[0][descripcion]'), ' Descripción A\noriginal ');
    assert.equal(datos.get('adjuntos[1][archivo]'), c); assert.equal(datos.get('adjuntos[1][descripcion]'), 'Descripción C');
    assert.equal(datos.has('adjuntos[12][archivo]'), false); assert.equal(datos.has('adjuntos[]'), false);
    // Simula mutación externa fuera de los controles bloqueados: el mapping debe seguir usando la copia enviada.
    w.archivos = [{ ...w.archivos[1] }, { ...w.archivos[0] }];
    originalC.id = 999; originalC.descripcion = 'Cambio posterior en el objeto anterior';
    pendiente.resolve(json({ errors: { [`adjuntos.1.${campo}`]: ['Error en C'] } }, 422)); await envio;
    const entradaC = w.archivos.find((e) => e.id === 12), entradaA = w.archivos.find((e) => e.id === 10);
    assert.equal(w.paso, 3); assert.equal(entradaC.archivo, c); assert.equal(entradaC.descripcion, 'Descripción C');
    assert.equal(entradaC[campo === 'archivo' ? 'errorServidor' : 'errorDescripcionServidor'], 'Error en C');
    assert.equal(entradaC[campo === 'archivo' ? 'errorDescripcionServidor' : 'errorServidor'], undefined);
    assert.equal(entradaA.errorServidor, undefined); assert.equal(entradaA.errorDescripcionServidor, undefined);
    assert.equal(w.claveIdempotencia, configuracion.uuid);
});

test('V2: reemplazo previo envía sólo File y descripción nuevos; FormData conserva el texto local exacto', async () => {
    entornoConfirmacion(); let datos;
    const w = enPaso4(async (url, opciones) => { datos = opciones.body; return exito(); });
    const viejo = w.archivos[0].archivo, idViejo = w.archivos[0].id;
    const nuevo = new File(['nuevo'], 'nuevo.pdf', { type: 'application/pdf' });
    w.reemplazarArchivo(idViejo, nuevo); assert.equal(w.archivos[0].descripcion, '');
    w.corregirDescripcion(w.archivos[0].id, '  Descripción nueva\ncon salto  ');
    await w.confirmarTurno();
    assert.equal(datos.get('adjuntos[0][archivo]'), nuevo); assert.notEqual(datos.get('adjuntos[0][archivo]'), viejo);
    assert.equal(datos.get('adjuntos[0][descripcion]'), '  Descripción nueva\ncon salto  ');
    assert.equal(w.archivos[0].descripcion, '  Descripción nueva\ncon salto  ');
    assert.equal([...datos.keys()].length, 6); assert.equal(datos.has('adjuntos[1][archivo]'), false);
});

test('V2: estado inválido programático impide POST incluso estando en revisión', async () => {
    for (const invalidar of [
        (w) => { w.motivo = ' '; }, (w) => { w.motivo = 'x'.repeat(501); },
        (w) => { w.archivos[0].descripcion = ' '; }, (w) => { w.archivos[0].descripcion = 'x'.repeat(501); },
        (w) => { w.archivos[0].errorServidor = 'Archivo rechazado'; },
        (w) => { w.archivos[0].errorDescripcionServidor = 'Descripción rechazada'; },
        (w) => { w.erroresServidor.adjuntos = 'Conjunto rechazado'; },
        (w) => { w.inicioProfesionales = 'otro inicio'; }, (w) => { w.kinesiologaId = null; },
        (w) => { w.confirmacionBloqueada = true; }, (w) => { w.enviando = true; },
        (w) => { w.consolidado = true; }, (w) => { w.redireccionando = true; },
    ]) {
        const w = enPaso4(() => assert.fail('No debe enviarse un POST inválido'));
        invalidar(w); assert.equal(w.puedeConfirmar, false); await w.confirmarTurno();
    }
});

for (const destino of [1, 2, 3]) test(`V2: editar desde revisión vuelve a Paso ${destino} sin borrar selección ni consultar`, () => {
    const w = seleccionCompleta(), antes = capturaLocal(w), intervalo = w.intervaloSeleccionado;
    w.paso = 4; w.editarDesdeRevision(destino);
    assert.equal(w.paso, destino); assert.deepEqual(capturaLocal(w), antes); assert.deepEqual(w.intervaloSeleccionado, intervalo);
    w.paso = 4; w.enviando = true; w.editarDesdeRevision(destino); assert.equal(w.paso, 4);
    w.enviando = false; w.editarDesdeRevision(0); w.editarDesdeRevision(4); assert.equal(w.paso, 4);
    w.paso = 2; w.editarDesdeRevision(1); assert.equal(w.paso, 2);
});
