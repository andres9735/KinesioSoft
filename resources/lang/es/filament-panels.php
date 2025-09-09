<?php

return [
    'pages' => [
        'dashboard' => [
            'title' => 'Escritorio',
        ],
        'auth' => [
            'login' => [
                'heading' => 'Iniciar sesión',
                'buttons' => [
                    'submit' => ['label' => 'Entrar'],
                ],
                'form' => [
                    'email' => ['label' => 'Correo electrónico'],
                    'password' => ['label' => 'Contraseña'],
                    'remember' => ['label' => 'Recordarme'],
                ],
            ],
        ],
    ],

    'widgets' => [
        'account' => [
            'heading' => 'Bienvenida/o',
            'actions' => [
                'logout' => ['label' => 'Salir'],
            ],
        ],
        'filament_info' => [
            'title' => 'Información de Filament',
        ],
    ],
];
