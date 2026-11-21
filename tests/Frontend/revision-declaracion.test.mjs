import test from 'node:test';
import assert from 'node:assert/strict';
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
