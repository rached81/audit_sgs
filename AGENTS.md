# AGENTS.md

## Cursor Cloud specific instructions

### Overview
This is a **Laravel 12 Stock Management & Audit System (SGS)** for managing inventory/stock data. It uses PHP 8.3, MySQL, and Vite (Tailwind CSS 4) for frontend assets.

### System dependencies (pre-installed in snapshot)
- PHP 8.3 with extensions: pdo_mysql, mbstring, xml, curl, zip, gd, bcmath, intl, sqlite3
- Composer 2.x
- MySQL 8.0
- Node.js 22.x + npm

### Starting MySQL
MySQL must be started manually before running the app:
```
sudo mkdir -p /var/run/mysqld && sudo chown mysql:mysql /var/run/mysqld
sudo mysqld --user=mysql --datadir=/var/lib/mysql &
```
Wait a few seconds for it to be ready, then verify with `sudo mysql -u root -e "SELECT 1"`.

### Environment setup
- `.env` is gitignored. If missing, copy `.env.exemple` to `.env` and adjust the DB_* vars to point to the local MySQL instance with the `sgs_db` schema, root user, no password.
  - Run `php artisan key:generate` after creating `.env`
  - Session, queue, and cache drivers should all be set to use MySQL-backed storage.
- The `sgs_db` MySQL schema must exist: `sudo mysql -u root -e "CREATE SCHEMA IF NOT EXISTS sgs_db;"`
- Run `php artisan migrate --force` after schema setup.

### Composer install caveat
The `yajra/laravel-oci8` package requires the `oci8` PHP extension (Oracle). Since Oracle is not available locally, always install with:
```
composer install --ignore-platform-req=ext-oci8
```

### Running the development server
The `composer dev` script starts all services concurrently (Laravel server, queue worker, log viewer, Vite):
```
composer dev
```
Or individually:
- `php artisan serve --host=0.0.0.0 --port=8000` — Laravel app
- `npx vite --host 0.0.0.0 --port 5173` — Vite dev server
- `php artisan queue:listen --tries=1` — Queue worker (for Excel imports)

### Lint & Tests
- **Lint**: `./vendor/bin/pint --test` (29 pre-existing style issues in the codebase)
- **Tests**: `php artisan test` (uses SQLite :memory: per phpunit.xml, no MySQL needed for tests)
- The feature test for `GET /` expects 200 but gets 302 because `/` redirects to login — this is a pre-existing test issue, not a bug.

### Auth
- Login uses `matricule` + password (not email)
- Create an admin user via: `php artisan user:create-admin` (interactive) or via tinker
- Default admin for dev: matricule=`admin`, password=`password`

### Key routes
- `/login` — Login page
- `/import` — Stock import (upload Excel files)
- `/consultation` — Browse imported stock data
- `/audit` — Compare EF vs GD stock data
- `/users` — User management (admin only)
