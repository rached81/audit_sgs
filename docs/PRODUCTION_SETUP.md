# Guide de preparation production

Ce document decrit les prerequis techniques et les etapes minimales pour demarrer l'application en production.

## 1) Versions minimales recommandees

- PHP: `8.2` minimum (contrainte projet: `composer.json` -> `php: ^8.2`)
- MySQL: `8.0+` (recommande)
- MariaDB: `10.3+` (recommande)
- Node.js: `20+` (recommande pour Vite moderne)
- Composer: `2.x`

Extensions PHP conseillees:
- `pdo_mysql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`

## 2) Configuration base de donnees (MySQL/MariaDB)

### 2.1 Activer `local_infile`

Le projet utilise `PDO::MYSQL_ATTR_LOCAL_INFILE` via `MYSQL_ATTR_LOCAL_INFILE` dans `config/database.php`.

Verifier:

```sql
SHOW GLOBAL VARIABLES LIKE 'local_infile';
```

Activer temporairement:

```sql
SET GLOBAL local_infile = 1;
```

Activer de facon persistante dans la conf serveur:
- MySQL: `my.cnf` ou `mysqld.cnf`
- MariaDB: `my.cnf`

Ajouter:

```ini
[mysqld]
local_infile=1
```

Puis redemarrer le service SQL.

### 2.2 Droits SQL minimaux

Le compte applicatif doit avoir les droits necessaires sur la base:
- `SELECT, INSERT, UPDATE, DELETE`
- `CREATE, ALTER, DROP, INDEX`

Exemple:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX
ON audit_sgs.* TO 'audit_sgs_user'@'%';
FLUSH PRIVILEGES;
```

## 3) Variables `.env` essentielles

Exemple minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=audit_sgs
DB_USERNAME=audit_sgs_user
DB_PASSWORD=mot_de_passe_fort
MYSQL_ATTR_LOCAL_INFILE=true

CACHE_STORE=redis
SESSION_DRIVER=database

# Super admin pour logs sensibles (Import Logs)
IMPORT_SUPER_ADMIN_MATRICULES=19684

# Bootstrap seed data (premier demarrage)
BOOTSTRAP_ADMIN_MATRICULE=19684
BOOTSTRAP_ADMIN_NOM=BENKHALIFA
BOOTSTRAP_ADMIN_PRENOM=Rached
BOOTSTRAP_ADMIN_EMAIL=rached.benkhalifa@sgs.tn
BOOTSTRAP_ADMIN_USERNAME=rached19684
BOOTSTRAP_ADMIN_UNITE=DSI
BOOTSTRAP_ADMIN_TEMP_PASSWORD=ChangeMe#19684
```

## 4) Installation et preparation

Depuis la racine projet:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.exemple .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate --force --seed
php artisan optimize
```

## 5) Donnees obligatoires au premier demarrage (via Seeder)

Le projet contient un seeder de bootstrap:
- `Database\\Seeders\\ProductionBootstrapSeeder`

Ce seeder:
- cree (ou met a jour) l'utilisateur **Rached BENKHALIFA**
- force `must_change_password = 1` (changement au premier login)
- restaure le compte s'il etait en suppression logique

Commandes:

```bash
php artisan db:seed --class=Database\\Seeders\\ProductionBootstrapSeeder --force
```

Ou directement pendant migration:

```bash
php artisan migrate --force --seed
```

## 6) Verification post-deploiement

- Login avec `19684` -> redirection attendue vers changement mot de passe initial.
- Import test -> verifier progression + resultat.
- Consultation -> verifier affichage + export.
- Audit -> verifier cohérence ecran/export.
- Import logs -> accessible uniquement super admin.

## 7) Deploiement pro (simple et fiable)

Approche recommandee:
- 1 dossier de release par livraison (ex: `/var/www/audit_sgs/releases/2026xxxx`)
- lien symbolique `current` vers la release active
- rollback rapide en re-pointant `current` vers release precedente

Sequence type:

```bash
composer run deploy:prod
```

Puis redemarrer PHP-FPM (et Redis si necessaire cache/session).

## 8) Services runtime recommandes

- Web: Nginx ou Apache + PHP-FPM
- Redis: **uniquement si** vous utilisez `CACHE_STORE=redis` (ou `QUEUE_CONNECTION=redis`). Sinon, en local sans Redis, mettre `CACHE_STORE=file` et `QUEUE_CONNECTION=sync` dans `.env` pour eviter `RedisException: connection refused`.
- Rotation logs applicatifs et monitoring erreurs

### Erreur RedisException (connexion refusee)

Causes typiques: `CACHE_STORE=redis` alors que le service Redis n'est pas demarre, ou mauvais `REDIS_HOST` / `REDIS_PORT`.

**Correctif rapide (developpement / serveur sans Redis):** dans `.env` :

```env
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Puis `php artisan optimize:clear`.

**Correctif production:** installer et demarrer Redis (`redis-server`), puis `CACHE_STORE=redis` si vous souhaitez le cache en Redis.

