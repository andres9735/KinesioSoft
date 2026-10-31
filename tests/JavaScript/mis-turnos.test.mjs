import assert from 'node:assert/strict';
import test from 'node:test';
import misTurnos from '../../resources/js/turnos/mis-turnos.js';

const config = {
    referencia: 'referencia-autenticada-fixture', csrfToken: 'csrf-fixture',
    urlConfirmar: '/turnos/mis-turnos/1/confirmar-asistencia', urlCancelar: '/turnos/mis-turnos/1/cancelar',
    urlDetalle: '/turnos/mis-turnos/1?estado=PENDIENTE&proximos_page=2', resumen: 'Turno de prueba.',
};
const respuesta = (datos, status = 200) => new Response(JSON.stringify(datos), { status, headers: { 'Content-Type': 'application/json' } });
function preparar({ decision = { isConfirmed: true, value: '' }, fetchImpl, obtenerSwal, timeoutMs } = {}) {
    const envios = [], dialogos = [], navegaciones = [];
    const swal = { fire: async (opciones) => { dialogos.push(opciones); return dialogos.length === 1 ? decision : {}; } };
    const componente = misTurnos(config, {
        obtenerSwal: obtenerSwal ?? (() => swal),
        fetchImpl: async (...args) => {
            envios.push(args);
            return fetchImpl ? fetchImpl(...args) : respuesta({ code: args[0] === config.urlCancelar ? 'turno_cancelado' : 'asistencia_confirmada', es_replay: false, message: 'Resultado persistido.' });
        },
        navegar: (url) => navegaciones.push(url),
        timeoutMs,
    });
    return { componente, envios, dialogos, navegaciones };
}

for (const operacion of ['confirmar', 'cancelar']) {
    test(`${operacion}: confirmación explícita, un POST con CSRF y refresco del detalle`, async () => {
        const x = preparar();
        await x.componente.solicitar(operacion);
        assert.equal(x.envios.length, 1);
        const [url, opciones] = x.envios[0];
        assert.equal(url, operacion === 'cancelar' ? config.urlCancelar : config.urlConfirmar);
        assert.equal(opciones.method, 'POST');
        assert.equal(opciones.credentials, 'same-origin');
        assert.equal(opciones.headers['X-CSRF-TOKEN'], config.csrfToken);
        const cuerpo = JSON.parse(opciones.body);
        assert.equal(cuerpo.referencia_programacion, config.referencia);
        assert.deepEqual(Object.keys(cuerpo), operacion === 'cancelar' ? ['referencia_programacion', 'motivo'] : ['referencia_programacion']);
        assert.equal(x.dialogos[1].icon, 'success');
        assert.deepEqual(x.navegaciones, [config.urlDetalle]);
        assert.equal(x.componente.ocupado, false);
    });
    for (const decision of [{ isConfirmed: false }, { isDismissed: true }, {}]) {
        test(`${operacion}: desistir/cerrar no envía solicitudes ${JSON.stringify(decision)}`, async () => {
            const x = preparar({ decision });
            await x.componente.solicitar(operacion);
            assert.equal(x.envios.length, 0);
            assert.equal(x.navegaciones.length, 0);
            assert.equal(x.componente.ocupado, false);
            assert.equal(x.componente.necesitaActualizar, false);
        });
    }
}

test('cancelar ofrece textarea opcional y transmite el motivo sin convertirlo en HTML', async () => {
    const motivo = '<script>fixture</script>';
    const x = preparar({ decision: { isConfirmed: true, value: motivo } });
    await x.componente.solicitar('cancelar');
    assert.equal(x.dialogos[0].input, 'textarea');
    assert.match(x.dialogos[0].inputLabel, /opcional/);
    assert.equal(JSON.parse(x.envios[0][1].body).motivo, motivo);
    assert.equal(x.dialogos[0].html, undefined);
});

test('doble clic y otra operación simultánea quedan bloqueados mientras se espera respuesta', async () => {
    let resolver;
    const pendiente = new Promise((resolve) => { resolver = resolve; });
    const x = preparar({ fetchImpl: () => pendiente });
    const primera = x.componente.solicitar('confirmar');
    await Promise.resolve();
    assert.equal(x.componente.ocupado, true);
    assert.equal(x.componente.enviando, true);
    await x.componente.solicitar('confirmar');
    await x.componente.solicitar('cancelar');
    x.componente.actualizar();
    assert.equal(x.envios.length, 1);
    assert.equal(x.navegaciones.length, 0);
    assert.equal(x.dialogos.length, 1);
    resolver(respuesta({ code: 'asistencia_confirmada', es_replay: false, message: 'Persistido.' }));
    await primera;
    assert.equal(x.componente.enviando, false);
    assert.equal(x.componente.ocupado, false);
});

for (const swal of [null, {}, { fire: undefined }]) {
    test(`SweetAlert2 no disponible no provoca envío automático ${JSON.stringify(swal)}`, async () => {
        const x = preparar({ obtenerSwal: () => swal });
        await x.componente.solicitar('confirmar');
        assert.equal(x.envios.length, 0);
        assert.match(x.componente.mensaje, /abrir la confirmación/);
    });
}

for (const status of [409, 422]) {
    test(`rechazo ${status}: recupera el GET vigente, sin repetir la transición`, async () => {
        const x = preparar({ fetchImpl: () => respuesta({ message: 'NO MOSTRAR: SQL fixture' }, status) });
        await x.componente.solicitar('cancelar');
        assert.equal(x.envios.length, 1);
        assert.equal(x.dialogos[1].icon, 'warning');
        assert.equal(x.dialogos.some((d) => d.icon === 'success'), false);
        assert.deepEqual(x.navegaciones, [config.urlDetalle]);
        assert.equal(x.componente.mensaje.includes('SQL'), false);
        assert.equal(x.componente.ocupado, false);
        await x.componente.solicitar('cancelar');
        assert.equal(x.envios.length, 1);
    });
}

test('replay se comunica como operación previa, sin éxito nuevo', async () => {
    const x = preparar({ fetchImpl: () => respuesta({ code: 'asistencia_confirmada', es_replay: true, message: 'Ya confirmado.' }) });
    await x.componente.solicitar('confirmar');
    assert.equal(x.dialogos[1].icon, 'info');
    assert.match(x.dialogos[1].title, /ya realizada/);
});

for (const [code, mensaje] of [['fuera_de_plazo', /12 horas.*consultorio/], ['inicio_no_futuro', /horario de inicio/]]) {
    test(`rechazo ${code}: comunica la restricción funcional sin volcar mensajes arbitrarios`, async () => {
        const x = preparar({ fetchImpl: () => respuesta({ code, message: 'NO VOLCAR SQL' }, 422) });
        await x.componente.solicitar('cancelar');
        assert.match(x.componente.mensaje, mensaje);
        assert.doesNotMatch(x.componente.mensaje, /SQL/);
        assert.equal(x.envios.length, 1);
        assert.deepEqual(x.navegaciones, [config.urlDetalle]);
    });
}

for (const [nombre, fallo] of [
    ['respuesta perdida', () => { throw new Error('DETALLE PRIVADO'); }],
    ['HTML de login', () => new Response('<html>Login</html>')],
    ['respuesta parcial', () => respuesta({ code: 'asistencia_confirmada', message: 'No acredita éxito' })],
    ['código de otra operación', () => respuesta({ code: 'turno_cancelado', es_replay: false, message: 'No corresponde' })],
    ['falla técnica', () => respuesta({ message: 'SQL fixture' }, 500)],
    ['acceso perdido', () => respuesta({ message: 'PRIVADO' }, 403)],
]) {
    test(`${nombre}: no afirma éxito ni reintenta; permite consultar el estado`, async () => {
        const x = preparar({ fetchImpl: fallo });
        await x.componente.solicitar('confirmar');
        assert.equal(x.envios.length, 1);
        assert.equal(x.dialogos.length, 1);
        assert.equal(x.navegaciones.length, 0);
        assert.equal(x.componente.necesitaActualizar, true);
        assert.equal(x.componente.enviando, false);
        assert.equal(x.componente.ocupado, false);
        assert.doesNotMatch(x.componente.mensaje, /PRIVADO|SQL|éxito|Persistido/);
        await x.componente.solicitar('confirmar');
        assert.equal(x.envios.length, 1);
        x.componente.actualizar();
        assert.deepEqual(x.navegaciones, [config.urlDetalle]);
    });
}

test('una falla al abrir el diálogo no envía una operación', async () => {
    const x = preparar({ obtenerSwal: () => ({ fire: () => { throw new Error('fallo de diálogo'); } }) });
    await x.componente.solicitar('cancelar');
    assert.equal(x.envios.length, 0);
    assert.equal(x.componente.ocupado, false);
    assert.match(x.componente.mensaje, /No se envió la operación/);
    assert.doesNotMatch(x.componente.mensaje, /No pudimos confirmar el resultado/);
});

test('una respuesta que no llega termina en incertidumbre con consulta manual y sin reintento', async () => {
    const x = preparar({
        timeoutMs: 5,
        fetchImpl: (_url, { signal }) => new Promise((_resolve, reject) => {
            signal.addEventListener('abort', () => reject(new DOMException('Transporte interrumpido', 'AbortError')), { once: true });
        }),
    });
    await x.componente.solicitar('cancelar');
    assert.equal(x.envios.length, 1);
    assert.equal(x.componente.ocupado, false);
    assert.equal(x.componente.enviando, false);
    assert.equal(x.componente.necesitaActualizar, true);
    assert.equal(x.navegaciones.length, 0);
    assert.equal(x.dialogos.length, 1);
    x.componente.actualizar();
    assert.deepEqual(x.navegaciones, [config.urlDetalle]);
});

for (const [status, texto] of [[401, /iniciá sesión/], [403, /permiso/], [404, /ya no está disponible/], [419, /venció/]]) {
    test(`HTTP ${status} con HTML comunica rechazo conocido, conserva contexto y nunca reenvía`, async () => {
        const x = preparar({ fetchImpl: () => new Response('<html>NO EXPONER SQL</html>', { status }) });
        await x.componente.solicitar('cancelar');
        assert.match(x.componente.mensaje, texto);
        assert.doesNotMatch(x.componente.mensaje, /NO EXPONER|SQL|No pudimos confirmar el resultado/);
        assert.equal(x.componente.volverAlListado, [403, 404].includes(status));
        assert.equal(x.componente.enviando, false);
        assert.equal(x.componente.ocupado, false);
        assert.equal(x.componente.necesitaActualizar, true);
        await x.componente.solicitar('cancelar');
        assert.equal(x.envios.length, 1);
        assert.equal(x.dialogos.length, 1);
        assert.equal(x.navegaciones.length, 0);
    });
}

test('403 y 404 ofrecen volver al listado con sus filtros, sin insistir en el detalle rechazado', async () => {
    for (const status of [403, 404]) {
        const navegaciones = [];
        const componente = misTurnos({ ...config, urlVolver: '/turnos/mis-turnos?estado=PENDIENTE#proximos-titulo' }, {
            obtenerSwal: () => ({ fire: async () => ({ isConfirmed: true }) }),
            fetchImpl: () => respuesta({}, status), navegar: (url) => navegaciones.push(url),
        });
        await componente.solicitar('confirmar');
        componente.actualizar();
        assert.deepEqual(navegaciones, ['/turnos/mis-turnos?estado=PENDIENTE#proximos-titulo']);
    }
});

for (const status of [409, 422]) {
    test(`HTTP ${status} sin JSON sigue siendo un rechazo conocido y recupera el detalle`, async () => {
        const x = preparar({ fetchImpl: () => new Response('HTML PRIVADO', { status }) });
        await x.componente.solicitar('confirmar');
        assert.equal(x.dialogos[1].icon, 'warning');
        assert.doesNotMatch(x.componente.mensaje, /PRIVADO|incierto|No pudimos confirmar/);
        assert.deepEqual(x.navegaciones, [config.urlDetalle]);
        assert.equal(x.envios.length, 1);
    });
}

for (const [status, datos] of [
    [200, { code: 'asistencia_confirmada', es_replay: false, message: 'Confirmado.' }],
    [200, { code: 'asistencia_confirmada', es_replay: true, message: 'Ya confirmado.' }],
    [409, { code: 'programacion_modificada' }],
    [422, { code: 'fuera_de_plazo' }],
]) {
    test(`progreso termina antes del diálogo de resultado ${status}/${datos.es_replay ?? datos.code}`, async () => {
        let cerrarResultado;
        let dialogos = 0;
        const navegaciones = [];
        const componente = misTurnos(config, {
            obtenerSwal: () => ({ fire: () => {
                dialogos++;
                if (dialogos === 1) return Promise.resolve({ isConfirmed: true });
                assert.equal(componente.enviando, false);
                assert.equal(componente.ocupado, true);
                return new Promise((resolve) => { cerrarResultado = resolve; });
            } }),
            fetchImpl: async () => respuesta(datos, status), navegar: (url) => navegaciones.push(url),
        });
        const operacion = componente.solicitar('confirmar');
        for (let i = 0; !cerrarResultado && i < 20; i++) await new Promise((resolve) => setImmediate(resolve));
        assert.equal(typeof cerrarResultado, 'function');
        await componente.solicitar('cancelar');
        assert.equal(dialogos, 2);
        assert.equal(navegaciones.length, 0);
        cerrarResultado({});
        await operacion;
        assert.equal(componente.ocupado, false);
        assert.deepEqual(navegaciones, [config.urlDetalle]);
    });
}

test('errores en línea reciben foco después de recuperar los controles', async () => {
    const x = preparar({ fetchImpl: () => respuesta({}, 419) });
    let focos = 0;
    x.componente.$nextTick = (callback) => {
        assert.equal(x.componente.ocupado, false);
        callback();
    };
    x.componente.$refs = { resultado: { focus: () => { focos++; } } };
    await x.componente.solicitar('confirmar');
    assert.equal(focos, 1);
    assert.match(x.componente.mensaje, /venció/);
});

for (const status of [200, 409, 422]) {
    test(`un fallo del diálogo final no vuelve incierto el resultado HTTP conocido ${status}`, async () => {
        let dialogos = 0;
        const x = preparar({
            obtenerSwal: () => ({ fire: async () => {
                if (++dialogos === 1) return { isConfirmed: true };
                throw new Error('Falla visual privada');
            } }),
            fetchImpl: () => respuesta({ code: status === 200 ? 'asistencia_confirmada' : 'estado_no_permitido', es_replay: false, message: 'Confirmación persistida.' }, status),
        });
        await x.componente.solicitar('confirmar');
        assert.equal(x.envios.length, 1);
        assert.doesNotMatch(x.componente.mensaje, /No pudimos confirmar el resultado|privada/);
        assert.match(x.componente.mensaje, status === 200 ? /persistida/ : status === 409 ? /programación cambió/ : /estado actual/);
        assert.equal(x.componente.necesitaActualizar, true);
        assert.equal(x.componente.ocupado, false);
        assert.equal(x.componente.enviando, false);
        x.componente.actualizar();
        assert.deepEqual(x.navegaciones, [config.urlDetalle]);
    });
}
