<?php

return [
    // GA-02. No modificar .env para preparar ni probar este incremento.
    'cron' => env('TURNOS_RECORDATORIOS_CRON', '*/5 * * * *'),
    // Sin exclusión horaria adicional por defecto; ambos valores HH:mm o ambos null.
    'horario_inicio' => env('TURNOS_RECORDATORIOS_HORARIO_INICIO'),
    'horario_fin' => env('TURNOS_RECORDATORIOS_HORARIO_FIN'),
];
