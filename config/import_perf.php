<?php

return [
    // Nom de queue dediee a l'import (permet de separer les workers).
    'queue_name' => env('IMPORT_QUEUE_NAME', 'imports'),

    // Nombre de lignes traitees par chunk.
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 1000),

    // Active/desactive les logs de diagnostic par chunk.
    'enable_chunk_logs' => (bool) env('IMPORT_ENABLE_CHUNK_LOGS', true),
];
