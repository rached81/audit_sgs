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

## 4) Decision automatique vs validation manuelle
1. Si tous les champs requis ont une confiance `>= 90`:
- Import lance automatiquement.
2. Sinon:
- Affichage de la vue `import_mapping` pour validation/correction manuelle du mapping.

## 5) Lancement du traitement asynchrone
1. Le controleur appelle `doImport(...)`.
2. Si la table n'existe pas, creation via la commande artisan `stock:create-table`.
3. Dispatch du job queue `ImportStockJob`.
4. Redirection vers le formulaire avec la table a suivre (`import_table`).

## 6) Traitement dans `ImportStockJob`
1. Estimation du nombre total de lignes du fichier (hors ligne d'entete).
2. Ecriture des metriques de progression dans le cache:
- `import_total_[TABLE]`
- reset de `import_processed_[TABLE]`
3. Execution de `Excel::import(new StockImport(...))`.
4. Nettoyage du fichier temporaire si import termine.
5. En cas d'echec: enregistrement `import_error_[TABLE]` puis exception relancee.

## 7) Transformation et insertion dans `StockImport`
1. Lecture en chunks (`1000` lignes).
2. Increment de progression cache a chaque chunk.
3. Resolution des champs via mapping + fallback (`resolveValue`).
4. Normalisation numerique (`toDecimal`) pour `initial`, `entree`, `sortie`, `finale`, `pump`, `valeur`.
5. Regles de filtrage (`shouldSkip` / `skipRow`):
- Ligne sans `article`
- Lignes `Groupe...`
- Lignes `Total...`
- Lignes avec <= 2 colonnes non vides
6. Insertion SQL en lot dans la table cible.

## 8) Suivi de progression cote front
- Route: `GET /import/status?table=...`
- Source prioritaire: cache (`import_processed_...`, `import_total_...`)
- Fallback: `count(*)` SQL si le cache processed est absent
- Pourcentage calcule et borne a `100`

## 9) Points d'extension
- Ajouter de nouveaux synonymes dans `ColumnMapper::synonyms()`.
- Ajuster la tolerance fuzzy/contains dans `ColumnMapper::mapHeaders()`.
- Ajuster la fenetre de detection (`maxLines`) dans `FastHeaderDetector::detect()`.
- Ajuster la taille de chunk dans `StockImport::chunkSize()`.
