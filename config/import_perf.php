<?php

return [
    // Nom de queue dediee a l'import (permet de separer les workers).
    'queue_name' => env('IMPORT_QUEUE_NAME', 'imports'),

    // Nombre de lignes chargees par bloc lors de la lecture Excel brute.
    'normalize_chunk_size' => (int) env('IMPORT_NORMALIZE_CHUNK_SIZE', 20000),

    // Nombre de lignes inserees par lot SQL.
    'sql_batch_size' => (int) env('IMPORT_SQL_BATCH_SIZE', 5000),

    // Ancien alias conserve pour compatibilite.
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 1000),

    // Active/desactive les logs de diagnostic par chunk.
    'enable_chunk_logs' => (bool) env('IMPORT_ENABLE_CHUNK_LOGS', true),

    // Active/desactive le debug d'import (fichiers originaux/CSV nettoye + lignes skippees).
    'debug_enabled' => (bool) env('IMPORT_DEBUG_ENABLED', false),

    // Limite optionnelle du nombre de lignes skippees ecrites (utile si fichier tres gros).
    // Si 0 => pas de limite.
    'debug_max_skipped_rows' => (int) env('IMPORT_DEBUG_MAX_SKIPPED_ROWS', 0),

    // Nombre de runs archives a conserver par table (1 = uniquement le dernier).
    'debug_keep_runs_per_table' => (int) env('IMPORT_DEBUG_KEEP_RUNS_PER_TABLE', 1),
];
