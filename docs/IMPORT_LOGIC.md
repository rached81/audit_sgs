# Documentation: logique d'importation des stocks

## 1) Entrees du processus
- Route d'entree: `POST /import` (`import.process`)
- Parametres obligatoires: `annee`, `programme`, `reseau`, `file`
- Table cible generee: `RES_[PROGRAMME]_[RESEAU]_[ANNEE]`

## 2) Controle de coherence avant import
1. Validation du formulaire et du fichier (`xlsx`, `xls`, `csv`).
2. Verification de la table cible:
- Si la table existe et contient des lignes, l'import est bloque.
3. Stockage temporaire du fichier dans `temp_imports`.

## 3) Detection automatique des entetes
1. `FastHeaderDetector` lit jusqu'a 10 lignes (configurable).
2. Chaque ligne est analysee par `ColumnMapper`.
3. `ColumnMapper` calcule un score de confiance par champ requis:
- Match exact: `100`
- Synonyme: `95`
- Match partiel (contains): `85`
- Fuzzy faible (Levenshtein): `70`
4. La ligne avec le score global le plus eleve devient la ligne d'entetes.

## 4) Validation stricte des entetes
1. Les entetes sont valides si toutes les colonnes attendues existent:
- `ARTICLE`, `DESIGNATION`, `INITIAL`, `ENTREE`, `SORTIE`, `FINALE`, `PUMP`, `VALEUR`
2. Regles de comparaison:
- casse ignoree
- accents ignores
- colonnes supplementaires autorisees
3. Si une colonne attendue manque:
- import bloque avec un message d'erreur clair (pas de formulaire de mapping manuel).

## 5) Lancement du traitement asynchrone
1. Le controleur appelle `doImport(...)`.
2. Si la table n'existe pas, creation via la commande artisan `stock:create-table`.
3. L'import est lance dans un **processus PHP detache** via la commande `stock:run-import-job` (pas de worker queue requis).
4. Redirection vers le formulaire avec la table a suivre (`import_table`).

## 6) Traitement dans `ImportStockJob`
1. Normalisation du fichier source vers un CSV temporaire (`StockCsvImporter::normalizeToCsv`).
2. Ecriture des metriques de progression dans le cache:
- `import_total_[TABLE]`
- reset de `import_processed_[TABLE]`
- `import_status_[TABLE]`, `import_table_state_[TABLE]`
3. Insertion SQL depuis le CSV normalise (`StockCsvImporter::importNormalizedCsv`).
4. Nettoyage des fichiers temporaires en fin de traitement (hors mode debug).
5. En cas d'echec: enregistrement `import_error_[TABLE]` et statut `failed`.

## 7) Suivi de progression cote front
- Route: `GET /import/status?table=...`
- Source prioritaire: cache (`import_processed_...`, `import_total_...`)
- Fallback: `count(*)` SQL si le cache processed est absent
- Pourcentage calcule et borne a `100`

## 8) Points d'extension
- Ajuster la fenetre de detection (`maxLines`) dans `FastHeaderDetector::detect()`.
- Ajuster la validation stricte des entetes dans `StockImportController`.
- Ajuster `IMPORT_NORMALIZE_CHUNK_SIZE` et `IMPORT_SQL_BATCH_SIZE` dans `config/import_perf.php`.
