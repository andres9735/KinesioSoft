<?php

return [
    'components' => [
        'text_input' => [
            'actions' => [
                'hide_password' => ['label' => 'Ocultar'],
                'show_password' => ['label' => 'Mostrar'],
            ],
        ],
        'select' => [
            'no_search_results_message' => 'Sin resultados.',
            'placeholder' => 'Seleccione…',
            'search_prompt' => 'Escriba para buscar…',
        ],
        'file_upload' => [
            'buttons' => [
                'upload' => ['label' => 'Subir'],
                'remove' => ['label' => 'Quitar'],
            ],
        ],
    ],
    'actions' => [
        'save' => ['label' => 'Guardar'],
        'cancel' => ['label' => 'Cancelar'],
    ],
    'messages' => [
        'saved' => 'Guardado con éxito.',
    ],
];
