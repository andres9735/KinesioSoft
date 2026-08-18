import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

function configuracionSimple(select) {
    return {
        create: false,
        maxItems: 1,
        allowEmptyOption: true,
        placeholder: select.dataset.tomSelectPlaceholder || undefined,
    };
}

function configuracionEspecialidades(select) {
    return {
        plugins: ['remove_button'],
        create: false,
        maxItems: null,
        hideSelected: true,
        closeAfterSelect: false,
        openOnFocus: true,
        placeholder:
            select.dataset.tomSelectPlaceholder ||
            'Seleccione una o más especialidades',
        dropdownClass: 'ts-dropdown-especialidades',
        render: {
            no_results(data, escape) {
                return (
                    '<div class="no-results">' +
                    'No se encontraron resultados para "' +
                    escape(data.input) +
                    '"</div>'
                );
            },
        },
    };
}

function obtenerConfiguracion(select) {
    switch (select.dataset.tomSelect) {
        case 'especialidades':
            return configuracionEspecialidades(select);

        case 'simple':
        default:
            return configuracionSimple(select);
    }
}

export function inicializarTomSelect(select) {
    if (!select) {
        return null;
    }

    if (select.tomselect) {
        return select.tomselect;
    }

    return new TomSelect(
        select,
        obtenerConfiguracion(select)
    );
}

export function inicializarTomSelects(root = document) {
    return Array.from(
        root.querySelectorAll('[data-tom-select]')
    ).map(inicializarTomSelect);
}
