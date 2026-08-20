const relacionesInicializadas = new WeakSet();

function obtenerValorSeleccionado(select) {
    if (!select) {
        return '';
    }

    return select.tomselect
        ? select.tomselect.getValue()
        : select.value;
}

function obtenerPlaceholder(
    matriculaInput,
    colegioSeleccionado
) {
    if (colegioSeleccionado) {
        return (
            matriculaInput.dataset
                .matriculaPlaceholderConColegio ||
            'Ej: 0763'
        );
    }

    return (
        matriculaInput.dataset
            .matriculaPlaceholderSinColegio ||
        'Seleccione primero un colegio emisor'
    );
}

function registrarCambio(select, callback) {
    if (!select) {
        return;
    }

    if (select.tomselect) {
        select.tomselect.on('change', callback);
        return;
    }

    select.addEventListener('change', callback);
}

export function inicializarRelacionColegioMatricula(
    matriculaInput,
    root = document
) {
    if (
        !matriculaInput ||
        relacionesInicializadas.has(matriculaInput)
    ) {
        return;
    }

    const selectorColegio =
        matriculaInput.dataset.colegioMatriculaSource;

    if (!selectorColegio) {
        return;
    }

    const colegioSelect = root.querySelector(
        selectorColegio
    );

    if (!colegioSelect) {
        return;
    }

    const selectorJurisdiccion =
        matriculaInput.dataset
            .colegioMatriculaJurisdiccionSource;

    const jurisdiccionSelect = selectorJurisdiccion
        ? root.querySelector(selectorJurisdiccion)
        : null;

    const actualizar = () => {
        const colegioSeleccionado =
            obtenerValorSeleccionado(colegioSelect);

        matriculaInput.disabled =
            !colegioSeleccionado;

        matriculaInput.placeholder =
            obtenerPlaceholder(
                matriculaInput,
                colegioSeleccionado
            );
    };

    registrarCambio(
        colegioSelect,
        actualizar
    );

    /*
     * También se escucha la jurisdicción porque
     * jurisdiccion-colegio.js puede limpiar el colegio
     * de forma silenciosa al cambiar de jurisdicción.
     */
    registrarCambio(
        jurisdiccionSelect,
        actualizar
    );

    relacionesInicializadas.add(
        matriculaInput
    );

    actualizar();
}

export function inicializarRelacionesColegioMatricula(
    root = document
) {
    root.querySelectorAll(
        '[data-colegio-matricula-source]'
    ).forEach(matriculaInput => {
        inicializarRelacionColegioMatricula(
            matriculaInput,
            root
        );
    });
}
