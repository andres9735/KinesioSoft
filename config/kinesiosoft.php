<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cuenta inicial del sistema
    |--------------------------------------------------------------------------
    |
    | Esta cuenta se utiliza únicamente para inicializar una instalación
    | nueva de KinesioSoft con un usuario superadministrador.
    |
    */

    'bootstrap_superadmin' => [
        'name' => env(
            'KINESIOSOFT_SUPERADMIN_NAME',
            'Superadministrador'
        ),

        'apellido' => env(
            'KINESIOSOFT_SUPERADMIN_APELLIDO',
            'Principal'
        ),

        'email' => env('KINESIOSOFT_SUPERADMIN_EMAIL'),

        'password' => env('KINESIOSOFT_SUPERADMIN_PASSWORD'),
    ],

    'ejercicios' => [
        'recursos_directorio' => 'ejercicios/recursos',
    ],

];
