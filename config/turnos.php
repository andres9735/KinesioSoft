<?php

return [
    // Minutos mínimos de antelación respecto al inicio del turno
    // para poder CONFIRMAR o CANCELAR.
    'confirm_min_minutes' => (int) env('TURNOS_CONFIRM_MIN_MINUTES', 180),  // 3 h
    'cancel_min_minutes'  => (int) env('TURNOS_CANCEL_MIN_MINUTES', 1440), // 24 h
    'buffer_min_minutes'  => (int) env('TURNOS_BUFFER_MIN_MINUTES', 5),    // 🕐 5 min entre turnos (opcional)

    // TTL de los links firmados (horas) — lo seguís usando para otros mails si querés
    'mail_link_ttl_hours' => (int) env('TURNOS_MAIL_LINK_TTL_HOURS', 36),

    // ⚡ Config específica para ofertas de ADELANTAR turno
    'adelanto' => [
        // Si el turno ofrecido (hueco) es para MAÑANA → 2h para responder
        'ttl_next_day_hours'  => (int) env('TURNOS_ADELANTO_TTL_NEXT_DAY_HOURS', 2),

        // Si el turno ofrecido es para dentro de varios días → 12h para responder
        'ttl_many_days_hours' => (int) env('TURNOS_ADELANTO_TTL_MANY_DAYS_HOURS', 12),
    ],

    'default_duration'       => 45,
    'allow_custom_duration'  => false,
];
