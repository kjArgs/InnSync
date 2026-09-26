# InnSync Backend

Laravel backend for InnSync, a learning project for online room reservations and hotel stay management.

## Current Status

This folder contains the Laravel scaffold and default user, cache, and queue migrations. Hotel modules, reservation endpoints, and JWT authentication are planned but not implemented yet.

See the [project overview](../README.md) and [business rules](../documents/BUSINESS-RULES.md) for the intended scope.

## Requirements

- PHP 8.3 or later compatible with the Composer dependencies.
- Composer.
- PostgreSQL and the PHP PostgreSQL PDO extension (`pdo_pgsql`).

## Local Setup

From the repository root:

```sh
cd backend
composer install
```

Copy `.env.example` to `.env` if it does not already exist. In PowerShell:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Create a local PostgreSQL database and update `.env` with your connection details:

```dotenv
APP_NAME=InnSync
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=innsync
DB_USERNAME=postgres
DB_PASSWORD=your_local_password
```

The example environment currently uses port `3306`; replace it with your PostgreSQL port (normally `5432`). Keep `.env` credentials local.

For a new local installation, generate the application key, apply migrations, and start the server:

```sh
php artisan key:generate
php artisan migrate
php artisan serve
```

The server normally runs at `http://localhost:8000`. The Vue application runs separately; see the [frontend README](../frontend/README.md).

## Useful Commands

Run these from `backend/`:

| Command | Purpose |
|---|---|
| `composer test` | Clear cached configuration and run tests |
| `php artisan route:list` | List registered routes |
| `php artisan migrate` | Apply pending migrations |
| `php artisan queue:work` | Process queued jobs when needed |
| `vendor/bin/pint` | Format PHP code |

## Structure

| Directory | Purpose |
|---|---|
| `app/` | Models, controllers, and providers |
| `routes/` | Web routes and console commands |
| `config/` | Application configuration |
| `database/` | Migrations, factories, and seeders |
| `tests/` | Automated tests |
| `storage/` | Logs, cache, and generated files |

See the [root README](../README.md#disclaimer) for the learning-purpose disclaimer and ownership information.
