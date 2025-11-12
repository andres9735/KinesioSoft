<?php

return [
    // Minutos mínimos de antelación respecto al inicio del turno
    // para poder CONFIRMAR o CANCELAR.
    'confirm_min_minutes' => (int) env('TURNOS_CONFIRM_MIN_MINUTES', 180),  // 3 h
    'cancel_min_minutes'  => (int) env('TURNOS_CANCEL_MIN_MINUTES', 1440),  // 24 h
    'buffer_min_minutes'  => (int) env('TURNOS_BUFFER_MIN_MINUTES', 5),     // 🕐 5 min entre turnos (opcional)

    // TTL de los links firmados (horas)
    'mail_link_ttl_hours' => (int) env('TURNOS_MAIL_LINK_TTL_HOURS', 36),
];
