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
