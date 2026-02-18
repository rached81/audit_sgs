# Import performance et diagnostic

## Logs ajoutes
- Fichier: `storage/logs/import-YYYY-MM-DD.log`
- Format: logs structures avec `run_id`, `table`, `chunk_index`, `duration_ms`.

## Evenements traces
- `import.job.started`
- `import.job.step.count_rows.done`
- `import.job.step.cache_init.done`
- `import.job.step.excel_import.done`
- `import.job.step.cleanup.done`
- `import.job.finished`
- `import.job.failed`
- `import.chunk.processed`

## Variables de configuration
- `IMPORT_CHUNK_SIZE=1000`
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

## Workers: est-ce que ca accelere?
Oui, mais avec nuance:
- Plus de workers accelere le debit global (plusieurs jobs en parallele).
- Avec l'architecture actuelle, un import = un job principal: plusieurs workers n'accelerent pas beaucoup un seul gros fichier, mais accelerent plusieurs imports simultanes.

Commande exemple (Windows) pour 4 workers:
- Ouvrir 4 terminaux et lancer:
- `php artisan queue:work --queue=default --tries=1 --timeout=3600 --sleep=1`

## Recommandations pratiques
1. Utiliser Redis comme backend queue/cache pour de gros volumes (plus rapide que database queue/cache).
2. Tester `IMPORT_CHUNK_SIZE` entre `2000` et `5000` selon RAM/DB.
3. Verifier les indexes de la table cible (indexes utiles oui, surplus ralentit l'insertion).
4. Garder `IMPORT_ENABLE_CHUNK_LOGS=true` pour diagnostic, puis le passer a `false` en production stable.
5. Passer a un import queue par chunks (parallelisable) si objectif: accelerer un seul tres gros fichier.
