# Import performance et diagnostic

## Logs ajoutes
- Fichier: `storage/logs/import-YYYY-MM-DD.log`
- Format: logs structures avec `run_id`, `table`, `chunk_index`, `duration_ms`.

## Evenements traces
- `import.job.started`
- `import.job.step.count_rows.done`
- `import.job.step.cache_init.done`
- `import.job.step.parallel_chunks_dispatched.done`
- `import.job.finished`
- `import.job.failed`
- `import.chunk.processed`
- `import.finalize.done`

## Variables de configuration
- `IMPORT_NORMALIZE_CHUNK_SIZE=20000`
- `IMPORT_SQL_BATCH_SIZE=5000`
- `IMPORT_ENABLE_CHUNK_LOGS=true`
- `LOG_IMPORT_LEVEL=info`
- `LOG_IMPORT_DAYS=14`

Apres modification du `.env`, executer:
- `php artisan config:clear`

## Lire les lenteurs
Observer surtout dans `import.chunk.processed`:
- `mapping_duration_ms` (parsing/filtrage)
- `insert_duration_ms` (insertion SQL)
- `total_chunk_duration_ms` (temps global chunk)
- `memory_mb` (pression memoire)

Si `insert_duration_ms` augmente fortement au fil des chunks:
- probable cout SQL/index/fichiers DB en croissance.

## Workers: sont-ils necessaires?
Non pour le flux d'import actuel:
- L'import est execute via un processus PHP detache (`stock:run-import-job`).
- Aucun worker queue n'est requis pour traiter un import.
- Les gains de performance se jouent surtout sur:
  - taille de lot SQL (`IMPORT_SQL_BATCH_SIZE`)
  - taille de normalisation (`IMPORT_NORMALIZE_CHUNK_SIZE`)
  - IO DB + indexes + ressources serveur

## Recommandations pratiques
1. Utiliser Redis comme backend cache pour de gros volumes.
2. Tester `IMPORT_SQL_BATCH_SIZE` entre `2000` et `8000` selon RAM/DB.
3. Verifier les indexes de la table cible (indexes utiles oui, surplus ralentit l'insertion).
4. Garder `IMPORT_ENABLE_CHUNK_LOGS=true` pour diagnostic, puis le passer a `false` en production stable.
5. Monitorer CPU/RAM/IO MySQL pendant les imports lourds et ajuster les tailles de batch.
