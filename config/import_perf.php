<?php

return [
    // Nombre de lignes traitees par chunk.
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 1000),

    // Active/desactive les logs de diagnostic par chunk.
    'enable_chunk_logs' => (bool) env('IMPORT_ENABLE_CHUNK_LOGS', true),
];
