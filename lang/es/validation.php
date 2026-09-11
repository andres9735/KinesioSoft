<?php

return [

    'required' => 'El campo :attribute es obligatorio.',

    'string' => 'El campo :attribute debe ser texto.',

    'numeric' => 'El campo :attribute debe ser un número.',

    'date' => 'El campo :attribute debe ser una fecha válida.',

    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',

    'in' => 'El valor seleccionado para :attribute no es válido.',

    'regex' => 'El formato del campo :attribute no es válido.',

    'array' => 'El campo :attribute debe ser una lista válida.',

    'unique' => 'El valor del campo :attribute ya está registrado.',

    'min' => [
        'numeric' => 'El campo :attribute debe ser como mínimo :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe contener al menos :min elementos.',
    ],

    'max' => [
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
        'array' => 'El campo :attribute no debe contener más de :max elementos.',
    ],

    'gt' => [
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
    ],

    'attributes' => [
        'dni' => 'DNI',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'sexo' => 'sexo',
        'telefono' => 'teléfono',
        'direccion' => 'dirección',
        'contacto_emergencia_nombre' =>
            'nombre del contacto de emergencia',
        'contacto_emergencia_telefono' =>
            'teléfono del contacto de emergencia',

        'objetivo_salud' => 'objetivo de salud',
        'peso_kg' => 'peso',
        'altura_cm' => 'altura',
        'ocupacion' => 'ocupación',
        'nivel_actividad_fisica' =>
            'nivel de actividad física',
        'detalle_actividad_fisica' =>
            'detalle de actividad física',

        'antecedentes_respuesta' =>
            'respuesta sobre antecedentes',
        'antecedentes' => 'antecedentes',

        'cirugias_respuesta' =>
            'respuesta sobre cirugías',
        'cirugias' => 'cirugías',

        'alergias_respuesta' =>
            'respuesta sobre alergias',
        'alergias' => 'alergias',

        'medicacion_respuesta' =>
            'respuesta sobre medicación',
        'medicaciones' => 'medicaciones',
    ],

];
