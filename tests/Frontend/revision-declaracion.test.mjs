import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import revisionDeclaracion from '../../resources/js/historia-clinica/revision-declaracion.js';

const originales = {
    antecedentes: { items: [{ item_origen_id: 10, descripcion: 'Antecedente original' }] },
    alergias: { items: [] }, cirugias: { items: [] }, medicacion: { items: [] },
    altura_cm: { valor: '170.25' },
};

test('no convierte listas vacías en ausencia ni clasifica automáticamente', () => {
    const estado = revisionDeclaracion(originales);
    estado.dictamenes.antecedentes.dictamen = 'VALIDAR';
    const contrato = estado.contrato();
    assert.deepEqual(contrato.complemento_profesional.bloques, {});
    assert.deepEqual(contrato.dictamenes.antecedentes.clasificacion, {});
    assert.equal(contrato.dictamenes.alergias.dictamen, '');
    assert.equal(Object.hasOwn(contrato.complemento_profesional, 'familia'), false);
    assert.equal(Object.hasOwn(contrato, 'confirmar'), false);
});

test('cambiar de dictamen excluye correcciones inactivas del envío sin perder la edición', () => {
    const estado = revisionDeclaracion(originales);
    estado.dictamenes.altura_cm.dictamen = 'CORREGIR';
    estado.dictamenes.altura_cm.correccion.valor = '171.75';
    assert.equal(estado.contrato().dictamenes.altura_cm.correccion.valor, '171.75');
    estado.dictamenes.altura_cm.dictamen = 'OBSERVAR';
    assert.equal(Object.hasOwn(estado.contrato().dictamenes.altura_cm, 'correccion'), false);
    assert.equal(estado.dictamenes.altura_cm.correccion.valor, '171.75');
    assert.equal(originales.altura_cm.valor, '170.25');
});

test('conserva clasificación explícita, precisión y procedencia de correcciones', () => {
    const estado = revisionDeclaracion(originales);
    estado.dictamenes.antecedentes.dictamen = 'VALIDAR';
    estado.dictamenes.antecedentes.clasificacion[10] = 'familiares';
    estado.agregarCorreccion('alergias');
    estado.dictamenes.alergias.dictamen = 'CORREGIR';
    Object.assign(estado.dictamenes.alergias.correccion.items[0], { descripcion: 'Alergia precisada', procedencia: 'REFERIDO_PACIENTE' });
    const contrato = estado.contrato();
    assert.equal(contrato.dictamenes.antecedentes.clasificacion[10], 'familiares');
    assert.equal(contrato.dictamenes.alergias.correccion.items[0].procedencia, 'REFERIDO_PACIENTE');
    assert.equal(contrato.dictamenes.alergias.correccion.items[0].item_origen_id, null);
});

test('el relevamiento explícito y el contacto requieren decisiones independientes', () => {
    const estado = revisionDeclaracion(originales);
    estado.bloques.alergias.resultado = 'DESCONOCIDO';
    estado.actualizarFamilia = true;
    estado.familia.resultado = 'INFORMACION_NO_DISPONIBLE';
    estado.familia.incluir_contacto = true;
    const contrato = estado.contrato();
    assert.deepEqual(contrato.complemento_profesional.bloques.alergias, { resultado: 'DESCONOCIDO', items: [] });
    assert.equal(contrato.complemento_profesional.familia.incluir_contacto, false);
    assert.deepEqual(contrato.complemento_profesional.familia.integrantes, []);
});

test('recupera una propuesta para editar y no transmite identidades del cliente', () => {
    const anterior = { especialidad_id: 3, paciente_id: 99, dictamenes: {
        altura_cm: { dictamen: 'CORREGIR', correccion: { valor: '171.75' } },
    }, sustituciones_base: ['altura_cm'] };
    const contrato = revisionDeclaracion(originales, anterior).contrato();
    assert.equal(contrato.dictamenes.altura_cm.correccion.valor, '171.75');
    assert.deepEqual(contrato.sustituciones_base, ['altura_cm']);
    assert.equal(Object.hasOwn(contrato, 'paciente_id'), false);
});

const categorias = ['personales', 'familiares', 'cirugias', 'alergias', 'medicacion'];
const camposSalud = ['objetivo_salud', 'peso_kg', 'altura_cm', 'ocupacion', 'nivel_actividad_fisica', 'detalle_actividad_fisica'];
const basePrevia = () => ({ campos: Object.fromEntries(camposSalud.map(c => [c, null])),
    bloques: Object.fromEntries(categorias.map(t => [t, { resultado: 'NO_PRESENTA', items: [] }])),
    familia: { resultado: 'INFORMACION_NO_DISPONIBLE' } });
function completa(base = basePrevia(), datos = originales) {
    const estado = revisionDeclaracion(structuredClone(datos), {}, { redisenio: true, base, especialidades: [3] });
    estado.especialidad_id = 3;
    estado.procedencia_relevamiento = 'RELEVAMIENTO_PROFESIONAL';
    for (const decision of Object.values(estado.dictamenes)) Object.assign(decision, { dictamen: 'DESCARTAR', justificacion: 'Dato no admitido' });
    return estado;
}

test('el progreso admite versión previa sin aportes y no trata descartes justificados como errores', () => {
    const estado = completa();
    const progreso = estado.progreso();
    assert.equal(progreso.completas, 4);
    assert.equal(progreso.porcentaje, 100);
    assert.equal(progreso.contadores.DESCARTAR, 10);
    assert.deepEqual(progreso.problemas, []);
});

test('sin versión ni datos admitidos exige cinco relevamientos y núcleo familiar sin inferir ausencia', () => {
    const estado = completa(null);
    assert.equal(estado.progreso().problemas.length, 6);
    for (const tipo of categorias) estado.bloques[tipo].resultado = 'DESCONOCIDO';
    estado.actualizarFamilia = true;
    estado.familia.resultado = 'INFORMACION_NO_DISPONIBLE';
    assert.equal(estado.progreso().completas, 4);
    assert.deepEqual(estado.contrato().complemento_profesional.bloques.personales, { resultado: 'DESCONOCIDO', items: [] });
});

test('datos declarados admitidos resuelven el mínimo sin exigir aporte complementario redundante', () => {
    const estado = completa(null, { ...originales, alergias: { items: [{ item_origen_id: 15, descripcion: 'Dato declarado' }] } });
    estado.dictamenes.alergias.dictamen = 'VALIDAR';
    for (const tipo of categorias.filter(t => t !== 'alergias')) estado.bloques[tipo].resultado = 'NO_PRESENTA';
    estado.actualizarFamilia = true;
    estado.familia.resultado = 'SIN_INTEGRANTES';
    assert.equal(estado.progreso().completas, 4);
    assert.equal(Object.hasOwn(estado.contrato().complemento_profesional.bloques, 'alergias'), false);
});

test('las cuatro secciones se expanden independientemente sin alterar el contrato o valores ocultos', () => {
    const estado = completa();
    estado.dictamenes.altura_cm.correccion.valor = '172.25';
    estado.bloques.alergias.items.push({ descripcion: 'Aporte oculto', procedencia: 'REFERIDO_PACIENTE', estado_codificacion: 'PENDIENTE_CODIFICACION' });
    estado.familia.integrantes.push({ nombre_apellido: '', vinculo: 'Hermana' });
    const antes = structuredClone(estado.contrato());
    for (const id of ['declarados', 'complementos', 'familia']) estado.alternar(id);
    assert.ok(Object.values(estado.abiertas).every(Boolean));
    estado.alternar('salud');
    estado.alternar('salud');
    assert.deepEqual(estado.contrato(), antes);
    assert.equal(estado.dictamenes.altura_cm.correccion.valor, '172.25');
    assert.equal(estado.bloques.alergias.items[0].descripcion, 'Aporte oculto');
    assert.equal(estado.familia.integrantes[0].vinculo, 'Hermana');
});

test('las correcciones válidas y observaciones justificadas son completas; falta de nota es pendiente', () => {
    const estado = completa();
    Object.assign(estado.dictamenes.altura_cm, { dictamen: 'CORREGIR', correccion: { valor: '171.75' } });
    estado.dictamenes.ocupacion.dictamen = 'OBSERVAR';
    assert.equal(estado.progreso().completas, 4);
    assert.equal(estado.progreso().contadores.CORREGIR, 1);
    assert.equal(estado.progreso().contadores.OBSERVAR, 1);
    estado.dictamenes.ocupacion.justificacion = '   ';
    assert.equal(estado.progreso().secciones[0].estado, 'Pendiente');
    estado.intentoVistaPrevia = true;
    assert.equal(estado.progreso().secciones[0].estado, 'Requiere atención');
});

test('el progreso distingue precisión, clasificación, procedencia y sustitución explícita', () => {
    const base = basePrevia();
    base.campos.altura_cm = '170.25';
    const estado = completa(base);
    Object.assign(estado.dictamenes.altura_cm, { dictamen: 'CORREGIR', correccion: { valor: '170.255' } });
    estado.dictamenes.antecedentes.dictamen = 'VALIDAR';
    assert.ok(estado.progreso().problemas.some(p => p.campo === 'dictamenes.altura_cm.correccion.valor' && p.error));
    assert.ok(estado.progreso().problemas.some(p => p.campo === 'dictamenes.antecedentes.clasificacion.10'));
    estado.dictamenes.altura_cm.correccion.valor = '171.25';
    estado.sustituciones.push('altura_cm');
    estado.dictamenes.antecedentes.clasificacion[10] = 'familiares';
    estado.bloques.cirugias.resultado = 'INFORMADO';
    estado.agregarAporte('cirugias');
    estado.bloques.cirugias.items[0].descripcion = 'Aporte';
    assert.ok(estado.progreso().problemas.some(p => p.campo === 'bloques.cirugias.items.0.procedencia'));
    estado.bloques.cirugias.items[0].procedencia = 'REFERIDO_PACIENTE';
    assert.equal(estado.progreso().completas, 4);
});

test('no admite ausencia complementaria incompatible con antecedentes conservados', () => {
    const base = basePrevia();
    base.bloques.alergias = { resultado: 'INFORMADO', items: [{ descripcion: 'Previo', procedencia: 'REFERIDO_PACIENTE', estado_codificacion: 'PENDIENTE_CODIFICACION' }] };
    const estado = completa(base);
    estado.bloques.alergias.resultado = 'NO_PRESENTA';
    assert.equal(estado.progreso().secciones[2].estado, 'Requiere atención');
    estado.sustituciones.push('alergias');
    assert.equal(estado.progreso().secciones[2].estado, 'Completa');
});

test('el progreso considera el límite conjunto de antecedentes de todas las fuentes', () => {
    const base = basePrevia();
    base.bloques.cirugias = { resultado: 'INFORMADO', items: Array.from({ length: 30 }, (_, i) => ({ descripcion: `Previo ${i}`, procedencia: 'REFERIDO_PACIENTE', estado_codificacion: 'PENDIENTE_CODIFICACION' })) };
    const estado = completa(base);
    estado.bloques.cirugias.resultado = 'INFORMADO';
    estado.agregarAporte('cirugias');
    Object.assign(estado.bloques.cirugias.items[0], { descripcion: 'Otro', procedencia: 'REFERIDO_PACIENTE' });
    assert.ok(estado.progreso().problemas.some(p => p.campo === 'bloques.cirugias.items' && p.error));
    estado.bloques.cirugias.items[0].descripcion = 'Previo 0';
    assert.equal(estado.progreso().secciones[2].estado, 'Completa');
});

test('familia exige vínculo explícito del contacto y conserva identidad y aportes en el contrato', () => {
    const estado = completa(null);
    for (const tipo of categorias) estado.bloques[tipo].resultado = 'NO_PRESENTA';
    estado.actualizarFamilia = true;
    estado.familia.resultado = 'INFORMADO';
    estado.familia.incluir_contacto = true;
    assert.ok(estado.progreso().problemas.some(p => p.campo === 'familia.vinculo_contacto'));
    estado.familia.vinculo_contacto = 'Madre';
    assert.equal(estado.progreso().secciones[3].estado, 'Completa');
    const antes = JSON.stringify(estado.contrato());
    estado.progreso();
    assert.equal(JSON.stringify(estado.contrato()), antes);
    estado.familia.resultado = 'INFORMACION_NO_DISPONIBLE';
    assert.equal(estado.contrato().complemento_profesional.familia.incluir_contacto, false);
    assert.equal(estado.familia.incluir_contacto, true);
});

test('errores localizables tienen prioridad sin doble conteo y los ambiguos no se asignan', () => {
    const estado = completa();
    estado.dictamenes.ocupacion.justificacion = '';
    estado.erroresServidor = { 'dictamenes.ocupacion.justificacion': ['Se requiere una justificación.'], 'items.0.procedencia': ['Procedencia inválida.'] };
    const progreso = estado.progreso();
    assert.equal(progreso.problemas.length, 2);
    assert.equal(progreso.secciones[0].estado, 'Requiere atención');
    assert.equal(progreso.ambiguos.length, 1);
    assert.equal(estado.localizarError('items.0.procedencia'), null);
    assert.equal(estado.localizarError('complemento_profesional.bloques.alergias.resultado').campo, 'bloques.alergias.resultado');
});

test('la navegación prioriza errores, expande, desplaza y enfoca sin modificar la propuesta', () => {
    const estado = completa();
    estado.especialidad_id = '';
    estado.erroresServidor = { 'dictamenes.alergias.dictamen': ['Revisá el dictamen.'] };
    const antes = structuredClone(estado.contrato());
    const llamadas = [];
    const control = { dataset: { campo: 'dictamenes.alergias.dictamen' }, matches: () => false, getClientRects: () => [1],
        scrollIntoView: () => llamadas.push('scroll'), focus: () => llamadas.push('focus') };
    estado.$nextTick = f => f();
    estado.$root = { querySelector: () => null, querySelectorAll: () => [control] };
    estado.irPrimerPendiente();
    assert.equal(estado.abiertas.declarados, true);
    assert.deepEqual(llamadas, ['scroll', 'focus']);
    assert.deepEqual(estado.contrato(), antes);
});

test('editar un campo limpia sólo sus errores localizados y conserva los ambiguos', () => {
    const estado = completa();
    estado.erroresServidor = { 'dictamenes.ocupacion.justificacion': ['Nota requerida'], 'items.0.procedencia': ['General'] };
    estado.actualizarErrores({ target: { dataset: { campo: 'dictamenes.ocupacion.justificacion' } } });
    assert.deepEqual(estado.erroresServidor, { 'items.0.procedencia': ['General'] });
});

test('los errores nativos abren todas las secciones afectadas y enfocan sólo el primer inválido', () => {
    const estado = completa();
    const cola = [];
    const destinos = [];
    estado.$nextTick = f => cola.push(f);
    estado.navegar = (seccion, campo) => destinos.push([seccion, campo]);
    const antes = structuredClone(estado.contrato());
    estado.registrarInvalido({ target: { dataset: { campo: 'dictamenes.ocupacion.dictamen' }, validationMessage: 'Seleccioná una opción' } });
    estado.registrarInvalido({ target: { dataset: { campo: 'dictamenes.alergias.dictamen' }, validationMessage: 'Seleccioná una opción' } });
    assert.equal(estado.abiertas.salud, true);
    assert.equal(estado.abiertas.declarados, true);
    assert.deepEqual(destinos, [['salud', 'dictamenes.ocupacion.dictamen']]);
    cola.forEach(f => f());
    assert.equal(estado.enfocandoInvalido, false);
    assert.deepEqual(estado.contrato(), antes);
});

test('al inicializar errores se abren sus secciones sin persistencia ni nueva serialización', () => {
    const estado = revisionDeclaracion(originales, {}, { redisenio: true, errores: { 'familia.resultado': ['Falta relevamiento'], 'items.0.procedencia': ['Ambiguo'] } });
    estado.$nextTick = f => f();
    let destino;
    estado.navegar = (seccion, campo) => { destino = [seccion, campo]; };
    const antes = structuredClone(estado.contrato());
    estado.init();
    assert.equal(estado.abiertas.familia, true);
    assert.deepEqual(destino, ['familia', 'familia.resultado']);
    assert.equal(estado.progreso().ambiguos.length, 1);
    assert.deepEqual(estado.contrato(), antes);
});

test('los pendientes identifican el dato sin alterar contadores, destinos ni contrato', () => {
    const estado = completa();
    estado.dictamenes.objetivo_salud.dictamen = '';
    estado.dictamenes.peso_kg.dictamen = '';
    const progreso = estado.progreso();
    const contratoAntes = structuredClone(estado.contrato());
    const mensajes = progreso.problemas.map(p => estado.textoPendiente(p));
    assert.ok(mensajes.includes('Objetivo de salud: Seleccioná el dictamen profesional.'));
    assert.ok(mensajes.includes('Peso: Seleccioná el dictamen profesional.'));
    assert.equal(estado.textoPendiente({ campo: 'bloques.cirugias.resultado', mensajes: ['Completá el relevamiento.'] }), 'Cirugías: Completá el relevamiento.');
    assert.equal(estado.textoPendiente({ campo: 'familia.resultado', mensajes: ['Completá el relevamiento.'] }), 'Núcleo familiar: Completá el relevamiento.');
    assert.deepEqual(estado.progreso(), progreso);
    assert.deepEqual(estado.contrato(), contratoAntes);
});

test('la etiqueta distingue aportes e integrantes y conserva los errores ambiguos sin atribución', () => {
    const estado = completa();
    assert.equal(estado.textoPendiente({ campo: 'bloques.alergias.items.1.procedencia', mensajes: ['Seleccioná la procedencia individual.'] }), 'Alergias, aporte 2: Seleccioná la procedencia individual.');
    assert.equal(estado.textoPendiente({ campo: 'familia.integrantes.0.vinculo', mensajes: ['Indicá el vínculo.'] }), 'Núcleo familiar, integrante 1: Indicá el vínculo.');
    assert.equal(estado.textoPendiente({ campo: 'dictamenes.antecedentes.clasificacion.10', mensajes: ['Clasificá el antecedente.'] }), 'Antecedentes declarados, ítem #10: Clasificá el antecedente.');
    assert.equal(estado.textoPendiente({ campo: 'items.0.procedencia', mensajes: ['Procedencia inválida.'] }), 'Procedencia inválida.');
    assert.equal(estado.localizarError('items.0.procedencia'), null);
});

test('los avisos condicionales tienen ocultamiento CSS previo a Alpine sin ocultamiento permanente', () => {
    const vista = readFileSync(new URL('../../resources/views/historia-clinica/revision.blade.php', import.meta.url), 'utf8');
    const formulario = readFileSync(new URL('../../resources/views/historia-clinica/revision-formulario.blade.php', import.meta.url), 'utf8');
    const resumen = formulario.match(/<div\b[^>]*id="revision-errores-generales"[^>]*>/)?.[0];
    const ambiguos = formulario.match(/<p\b[^>]*x-show="progreso\(\)\.ambiguos\.length"[^>]*>/)?.[0];
    assert.ok(resumen);
    assert.ok(ambiguos);
    assert.match(vista, /#revision-formulario\s+\[x-cloak\]\s*\{\s*display:\s*none\s*!important;\s*\}/);
    for (const elemento of [resumen, ambiguos]) {
        assert.match(elemento, /\sx-cloak(?:\s|>)/);
        assert.doesNotMatch(elemento, /\shidden(?:\s|=|>)/);
        assert.doesNotMatch(elemento, /style="[^"]*display:\s*none/);
        assert.doesNotMatch(elemento, /class="[^"]*\bhidden\b/);
    }
    assert.match(resumen, /x-show="Object\.keys\(erroresServidor\)\.length"/);
    assert.ok(vista.indexOf('[x-cloak]') < vista.indexOf("@include('historia-clinica.revision-formulario'"));
});

test('la inicialización conserva las condiciones de visibilidad de errores reales y no fabrica errores', () => {
    const sinErrores = revisionDeclaracion(originales, {}, { redisenio: true });
    sinErrores.init();
    assert.equal(Object.keys(sinErrores.erroresServidor).length, 0);
    assert.equal(sinErrores.progreso().ambiguos.length, 0);
    const errores = { 'familia.resultado': ['Completá el relevamiento'], 'items.0.procedencia': ['Revisá la procedencia'] };
    const conErrores = revisionDeclaracion(originales, {}, { redisenio: true, errores });
    conErrores.$nextTick = f => f();
    conErrores.irPrimerPendiente = () => {};
    conErrores.init();
    assert.deepEqual(conErrores.erroresServidor, errores);
    assert.equal(Object.keys(conErrores.erroresServidor).length, 2);
    assert.equal(conErrores.progreso().ambiguos.length, 1);
    assert.equal(conErrores.abiertas.familia, true);
});

test('localiza categorías y filas inequívocas sin desplazar errores hacia otros controles', () => {
    const estado = completa();
    estado.dictamenes.alergias.dictamen = 'CORREGIR';
    estado.dictamenes.alergias.correccion.items = [{ descripcion: 'Alergia', procedencia: '', item_origen_id: null, tipo_destino: 'alergias' }];
    estado.bloques.cirugias.resultado = 'INFORMADO';
    estado.bloques.cirugias.items = [{ descripcion: '' }, { descripcion: 'Cirugía', procedencia: '' }];
    estado.actualizarFamilia = true;
    estado.familia = { resultado: 'INFORMADO', integrantes: [{}, { nombre_apellido: 'Integrante', vinculo: '' }], incluir_contacto: false };
    estado.erroresServidor = {
        'dictamenes.alergias.correccion.items.0.procedencia': ['Error Alergias'],
        'bloques.cirugias.items.1.procedencia': ['Error Cirugías'],
        'familia.integrantes.1.vinculo': ['Error Familia'],
    };
    const antes = JSON.stringify(estado.contrato());
    assert.ok(estado.mensajes('dictamenes.alergias.correccion.items.0.procedencia').includes('Error Alergias'));
    assert.ok(estado.mensajes('bloques.cirugias.items.1.procedencia').includes('Error Cirugías'));
    assert.ok(estado.mensajes('familia.integrantes.1.vinculo').includes('Error Familia'));
    assert.ok(!estado.mensajes('bloques.cirugias.items.0.procedencia').includes('Error Cirugías'));
    assert.ok(!estado.mensajes('familia.integrantes.0.vinculo').includes('Error Familia'));
    assert.deepEqual(estado.mensajes('dictamenes.cirugias.correccion.items.0.procedencia'), []);
    assert.equal(JSON.stringify(estado.contrato()), antes);
    const formulario = readFileSync(new URL('../../resources/views/historia-clinica/revision-formulario.blade.php', import.meta.url), 'utf8');
    const complementos = readFileSync(new URL('../../resources/views/historia-clinica/revision-complementos.blade.php', import.meta.url), 'utf8');
    for (const contenido of [formulario, complementos]) {
        assert.match(contenido, /:data-campo=/);
        assert.match(contenido, /:aria-describedby=/);
        assert.match(contenido, /x-text="mensajes\(/);
    }
});

test('un error de fila abre, desplaza y enfoca sólo su control sin cambiar la propuesta', () => {
    const estado = completa();
    estado.erroresServidor = { 'complemento_profesional.bloques.alergias.items.1.procedencia': ['Revisá esta procedencia'] };
    const antes = structuredClone(estado.contrato());
    const llamadas = [];
    const controles = ['bloques.cirugias.items.1.procedencia', 'bloques.alergias.items.0.procedencia', 'bloques.alergias.items.1.procedencia'].map(campo => ({
        dataset: { campo }, matches: () => false, getClientRects: () => [1],
        scrollIntoView: () => llamadas.push(['scroll', campo]), focus: () => llamadas.push(['focus', campo]),
    }));
    estado.$nextTick = f => f();
    estado.$root = { querySelector: () => null, querySelectorAll: () => controles };
    estado.init();
    assert.equal(estado.abiertas.complementos, true);
    assert.deepEqual(llamadas, [['scroll', 'bloques.alergias.items.1.procedencia'], ['focus', 'bloques.alergias.items.1.procedencia']]);
    assert.deepEqual(estado.contrato(), antes);
});

test('localiza grupos y sustituciones conocidas conservando errores históricos y generales', () => {
    const estado = completa();
    assert.deepEqual(estado.localizarError('sustituciones_base.altura_cm'), { seccion: 'salud', campo: 'sustituciones_base.altura_cm' });
    assert.equal(estado.localizarError('sustituciones_base.alergias').seccion, 'complementos');
    assert.equal(estado.localizarError('sustituciones_base.familia').seccion, 'familia');
    assert.equal(estado.localizarError('dictamenes.alergias.correccion.items.0').campo, 'dictamenes.alergias.correccion');
    assert.equal(estado.localizarError('dictamenes.alergias.correccion.items').campo, 'dictamenes.alergias.correccion');
    assert.equal(estado.localizarError('bloques.alergias.items.1.estado_codificacion').campo, 'bloques.alergias');
    for (const clave of ['items.0.procedencia', 'sustituciones_base', 'sustituciones_base.0', 'version_base', 'propuesta']) {
        assert.equal(estado.localizarError(clave), null);
    }
    estado.erroresServidor = { version_base: ['Error histórico'], propuesta: ['Error del conjunto'] };
    assert.equal(estado.progreso().ambiguos.length, 2);
    assert.deepEqual(estado.mensajes('bloques.alergias.items.0.procedencia'), []);
    assert.equal(revisionDeclaracion(originales).localizarError('sustituciones_base.familia'), null);
});
