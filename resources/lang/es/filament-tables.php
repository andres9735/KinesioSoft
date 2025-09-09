<?php

return [

    'actions' => [
        'edit' => [
            'label' => 'Editar',
        ],
        'delete' => [
            'label' => 'Borrar',
            'modal' => [
                'heading' => 'Borrar registro',
                'description' => '¿Seguro que quieres borrar este registro?',
                'actions' => [
                    'delete' => ['label' => 'Borrar'],
                ],
            ],
        ],
        'view' => [
            'label' => 'Ver',
        ],
    ],

    'bulk_actions' => [
        'delete' => [
            'label' => 'Borrar seleccionados',
            'modal' => [
                'heading' => 'Borrar registros seleccionados',
                'description' => 'Esta acción no se puede deshacer.',
                'actions' => [
                    'delete' => ['label' => 'Borrar'],
                ],
            ],
        ],
    ],

    'empty' => [
        'heading' => 'Sin resultados',
        'description' => 'No hay registros para mostrar.',
    ],

    'pagination' => [
        'label' => 'Paginación',
        'overview' => '{from}–{to} de {total} resultados',
    ],

    'filters' => [
        'label' => 'Filtros',
        'actions' => [
            'apply' => [
                'label' => 'Aplicar',
            ],
            'remove' => [
                'label' => 'Quitar filtro',
            ],
            'reset' => [
                'label' => 'Restablecer',
            ],
        ],
    ],

    'columns' => [
        'actions' => [
            'label' => 'Acciones',
        ],
    ],

];
