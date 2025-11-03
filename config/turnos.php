<?php

return [
    // Minutos mínimos de antelación respecto al inicio del turno
    // para poder CONFIRMAR o CANCELAR.
    'confirm_min_minutes' => env('TURNOS_CONFIRM_MIN_MINUTES', 180),  // 3 h
    'cancel_min_minutes'  => env('TURNOS_CANCEL_MIN_MINUTES', 1440),  // 24 h
];
