# Configuration des Limites de Upload

## Problème
Erreur `Illuminate\Http\Exceptions\PostTooLargeException` lors de l'upload de fichiers volumineux (200000+ lignes).

## Solutions Appliquées

### 1. Configuration Apache (.htaccess)
Fichier: `public/.htaccess`
- `upload_max_filesize = 1024M` (1 GB)
- `post_max_size = 1024M` (1 GB)
- `max_input_time = 3600` (1 heure)
- `max_execution_time = 3600` (1 heure)

### 2. Configuration PHP (.user.ini)
Fichier: `.user.ini` (racine du projet)
- `upload_max_filesize = 1024M`
- `post_max_size = 1024M`
- `max_input_time = 3600`
- `max_execution_time = 3600`
- `memory_limit = 2048M`

### 3. Validation Laravel
Fichier: `app/Http/Controllers/StockImportController.php`
- Ajout de la règle `max:1048576` (1 GB en KB) dans la validation du fichier

### 4. Interface Utilisateur
Fichier: `resources/views/import.blade.php`
- Affichage du message "Taille maximale: 1 GB" à l'utilisateur
- Correction du typo dans l'attribut accept (.cvs → .csv)

## Configuration Serveur (Si applicable)
Si vous utilisez Nginx au lieu d'Apache, ajoutez dans votre configuration:
```nginx
client_max_body_size 1024M;
```

## Vérification
Après les modifications, redémarrez votre serveur/application et testez avec un fichier de 200000 lignes.
