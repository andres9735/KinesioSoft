const relacionesInicializadas = new WeakSet();

function obtenerColegiosOriginales(colegioSelect) {
    return Array.from(colegioSelect.options)
        .filter(option => option.value !== '')
        .map(option => ({
            value: option.value,
            text: option.text,
            jurisdiccion: option.dataset.jurisdiccion,
        }));
}

function obtenerJurisdiccionSeleccionada(jurisdiccionSelect) {
    return jurisdiccionSelect.tomselect
        ? jurisdiccionSelect.tomselect.getValue()
        : jurisdiccionSelect.value;
}

function obtenerTextoPlaceholder(
    colegioSelect,
    jurisdiccionSeleccionada
) {
    if (jurisdiccionSeleccionada) {
        return (
            colegioSelect.dataset.colegioPlaceholderConJurisdiccion ||
            'Seleccione un colegio emisor'
        );
    }

    return (
        colegioSelect.dataset.colegioPlaceholderSinJurisdiccion ||
        'Primero seleccione una jurisdicción'
    );
}

function actualizarSelectNativo(
    colegioSelect,
    jurisdiccionSeleccionada
) {
    colegioSelect.disabled = !jurisdiccionSeleccionada;

    Array.from(colegioSelect.options).forEach(option => {
        if (option.value === '') {
            option.hidden = false;
            option.text = obtenerTextoPlaceholder(
                colegioSelect,
                jurisdiccionSeleccionada
            );
            return;
        }

        option.hidden =
            option.dataset.jurisdiccion !==
            jurisdiccionSeleccionada;
    });

    const opcionActual =
        colegioSelect.options[colegioSelect.selectedIndex];

    if (
        opcionActual &&
        opcionActual.value !== '' &&
        opcionActual.hidden
    ) {
        colegioSelect.value = '';
    }

    if (!jurisdiccionSeleccionada) {
        colegioSelect.value = '';
    }
}

function actualizarTomSelect(
    colegioSelect,
    colegiosOriginales,
    jurisdiccionSeleccionada
) {
    const colegioTom = colegioSelect.tomselect;
    const valorActual = colegioTom.getValue();

    colegioTom.clear(true);
    colegioTom.clearOptions();

    colegioTom.addOption({
        value: '',
        text: obtenerTextoPlaceholder(
            colegioSelect,
            jurisdiccionSeleccionada
        ),
    });

    if (!jurisdiccionSeleccionada) {
        colegioTom.disable();
        colegioTom.refreshOptions(false);
        return;
    }

    const colegiosFiltrados = colegiosOriginales
        .filter(
            colegio =>
                colegio.jurisdiccion ===
                jurisdiccionSeleccionada
        )
        .map(colegio => ({
            value: colegio.value,
            text: colegio.text,
        }));

    colegioTom.addOptions(colegiosFiltrados);
    colegioTom.enable();

    const opcionValida = colegiosFiltrados.find(
        colegio => colegio.value == valorActual
    );

    if (opcionValida) {
        colegioTom.setValue(valorActual, true);
    }

    colegioTom.refreshOptions(false);
}

export function inicializarRelacionJurisdiccionColegio(
    jurisdiccionSelect,
    root = document
) {
    if (
        !jurisdiccionSelect ||
        relacionesInicializadas.has(jurisdiccionSelect)
    ) {
        return;
    }

    const selectorColegio =
        jurisdiccionSelect.dataset
            .jurisdiccionColegioTarget;

    if (!selectorColegio) {
        return;
    }

    const colegioSelect = root.querySelector(
        selectorColegio
    );

    if (!colegioSelect) {
        return;
    }

    const colegiosOriginales =
        obtenerColegiosOriginales(colegioSelect);

    const actualizar = () => {
        const jurisdiccionSeleccionada =
            obtenerJurisdiccionSeleccionada(
                jurisdiccionSelect
            );

        if (colegioSelect.tomselect) {
            actualizarTomSelect(
                colegioSelect,
                colegiosOriginales,
                jurisdiccionSeleccionada
            );
            return;
        }

        actualizarSelectNativo(
            colegioSelect,
            jurisdiccionSeleccionada
        );
    };

    if (jurisdiccionSelect.tomselect) {
        jurisdiccionSelect.tomselect.on(
            'change',
            actualizar
        );
    } else {
        jurisdiccionSelect.addEventListener(
            'change',
            actualizar
        );
    }

    relacionesInicializadas.add(
        jurisdiccionSelect
    );

    actualizar();
}

export function inicializarRelacionesJurisdiccionColegio(
    root = document
) {
    root.querySelectorAll(
        '[data-jurisdiccion-colegio-target]'
    ).forEach(jurisdiccionSelect => {
        inicializarRelacionJurisdiccionColegio(
            jurisdiccionSelect,
            root
        );
    });
}
