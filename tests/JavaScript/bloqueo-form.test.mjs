import assert from 'node:assert/strict';
import test from 'node:test';
import {
    componerIntervaloCompleto,
    componerIntervaloParcial,
    reconstruirCaptura,
    resumirIntervalo,
    sumarDiasCalendario,
} from '../../resources/js/agenda/bloqueo-form.js';

test('un día completo usa medianoche del día siguiente como fin exclusivo', () => {
    assert.deepEqual(
        componerIntervaloCompleto('2026-10-10', '2026-10-10'),
        {
            inicioAt: '2026-10-10 00:00:00',
            finAt: '2026-10-11 00:00:00',
        },
    );
});

test('un rango completo incluye la fecha final visual sin usar fin artificial', () => {
    const intervalo = componerIntervaloCompleto('2026-10-10', '2026-10-12');

    assert.deepEqual(intervalo, {
        inicioAt: '2026-10-10 00:00:00',
        finAt: '2026-10-13 00:00:00',
    });
    assert.equal(JSON.stringify(intervalo).includes('23:59:59'), false);
});

test('la aritmética ISO cruza mes y año sin depender de la zona local', () => {
    assert.equal(sumarDiasCalendario('2026-10-31', 1), '2026-11-01');
    assert.equal(sumarDiasCalendario('2026-12-31', 1), '2027-01-01');
    assert.equal(sumarDiasCalendario('2027-01-01', -1), '2026-12-31');
});

test('el intervalo parcial multidiario se compone como un único intervalo continuo', () => {
    const intervalo = componerIntervaloParcial(
        '2026-10-10',
        '09:00',
        '2026-10-12',
        '12:00',
    );

    assert.deepEqual(intervalo, {
        inicioAt: '2026-10-10 09:00:00',
        finAt: '2026-10-12 12:00:00',
    });
    assert.equal(resumirIntervalo({
        modo: 'parcial',
        fechaInicio: '2026-10-10',
        horaInicio: '09:00',
        fechaFin: '2026-10-12',
        horaFin: '12:00',
    }), 'Desde 10/10/2026 09:00 hasta 12/10/2026 12:00, de forma continua.');
});

test('la captura se reconstruye desde el candidato canónico sin persistir el modo', () => {
    assert.deepEqual(
        reconstruirCaptura('2026-10-10 00:00:00-03:00', '2026-10-13 00:00:00-03:00'),
        {
            modo: 'completo',
            fechaInicio: '2026-10-10',
            fechaFin: '2026-10-12',
            horaInicio: '',
            horaFin: '',
        },
    );
});
