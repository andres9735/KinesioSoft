<?php

return [
    'disk' => 'documentos_clinicos',
    'directorio' => 'originales',
    'lock_timeout_segundos' => 5,
    'reconciliacion' => [
        'gracia_horas' => 24,
    ],
    'max_archivos' => 5,
    'max_bytes_por_archivo' => 10 * 1024 * 1024,
    'max_bytes_total' => 25 * 1024 * 1024,
    'mime_permitidos' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ],
];
