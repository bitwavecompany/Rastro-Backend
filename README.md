![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white)
![Pest](https://img.shields.io/badge/Tests-Pest%2FPHPUnit-16B98B?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-yellow?style=flat-square)
![Deploy](https://img.shields.io/badge/Deploy-Render-46E3B7?style=flat-square&logo=render&logoColor=white)
![Auth](https://img.shields.io/badge/Auth-Laravel%20Sanctum-FB503B?style=flat-square)
![DB Prod](https://img.shields.io/badge/DB%20Prod-Neon%20Serverless-00E599?style=flat-square&logo=neon&logoColor=white)

# Rastro Backend

REST API built with Laravel 13 to support the **Rastro** financial mobile app. Its core responsibilities are offline-first data synchronization, authentication with concurrent session control, and strict enforcement of business rules.

## Tech Stack

| Component | Technology |
|---|---|
| Language | PHP 8.3 (Strict Typed) |
| Framework | Laravel 13.x |
| Database (local) | PostgreSQL 16 via Docker / Sail |
| Database (production) | Neon (serverless PostgreSQL) |
| Authentication | Laravel Sanctum (dual-token: access + refresh) |
| Testing | Pest / PHPUnit |
| Deploy | Render (Web Service with Dockerfile) |

---

## Project Documentation

Read in this order before touching the code:

| Document | Contents |
|---|---|
| `_context/prd.md` | Source of truth for business rules and user plans |
| `_context/business-rules.md` | Detailed rules per entity and flow |
| `_context/data-model-backend.md` | DB schema, Eloquent models, enums, and canonical fields |
| `_context/api_contract.md` | Full contract for endpoints, payloads, and responses |
| `_context/backlog.md` | Requirements backlog and product roadmap |
| `AGENTS.md` | Mandatory rules for AI agents and verification checklist |

---

## Offline-First Architecture & Batch Sync

The mobile app operates without a network connection, writing to its local database (Dexie/IndexedDB). The backend is designed exclusively for this paradigm:

- **Zero Chatty CRUD:** There are no individual mutation endpoints for Accounts or Transactions.
- **Batch Sync (`POST /api/sync/push`):** The device packages its Outbox and sends a single request containing an array of operations. The backend processes everything inside a `DB::transaction`, evaluates business rules (plan limits, data consistency), and returns individual results per `opId`.
- **Soft Deletes:** Accounts and Transactions use logical deletion to preserve historical integrity and allow correct state synchronization.
- **Protected Default Account:** Every user has a permanent, immutable "Cash" account (`is_default = true`). The sync engine blocks with `403` any attempt to modify or delete it.

---

## Code Architecture (Clean Code + GoF)

```
app/
├── Actions/          # Use-case orchestration (e.g. ProcessBatchSyncAction)
├── Commands/Sync/    # Command pattern — one class per sync operation
├── Constants/        # ApiMessage and HttpCode — zero magic strings
├── DTOs/             # Immutable objects between Controller and Action
├── Enums/            # Strongly typed types (AccountType, TransactionType...)
├── Factories/        # SyncCommandFactory — Factory Method pattern
├── Http/
│   ├── Controllers/  # Thin layers: receive, map to DTO, respond
│   ├── Requests/     # Input validation
│   └── Resources/    # Full control over serialization to the client
├── Models/           # Eloquent with SoftDeletes and canonical accessors
└── Traits/           # ApiResponse — standard response envelope
```

**Applied principles:**
- **OCP (Open-Closed):** New sync operations = new Command + registration in Factory. `ProcessBatchSyncAction` is never touched.
- **SRP:** Controllers only map and respond. Logic lives in Actions.
- **Strict Typing:** `declare(strict_types=1)` is mandatory in every PHP file.
- **No magic strings:** Every API message comes from `ApiMessage`, every HTTP code from `HttpCode`, every type from `app/Enums`.

---

## Local Development Environment

### Requirements

- Docker Desktop running on the host machine
- PHP 8.3+ and Composer (only if running outside of Sail)

### Initial Setup

```bash
# 1. Copy environment variables
cp .env.example .env

# 2. Install dependencies
composer install

# 3. Start containers
./vendor/bin/sail up -d

# 4. Generate application key
./vendor/bin/sail artisan key:generate

# 5. Run migrations
./vendor/bin/sail artisan migrate

# 6. Create admin and test users
./vendor/bin/sail artisan db:seed --force
```

The seeder creates two users idempotently (`updateOrCreate`):

| Email | Password | Role | Plan |
|---|---|---|---|
| `admin@rastro.com` | `admin123` | `admin` | `premium` |
| `test@example.com` | `password` | `client` | `free` |

### Common Commands (Linux / WSL / Git Bash)

```bash
./vendor/bin/sail up -d          # Start containers
./vendor/bin/sail down           # Stop containers
./vendor/bin/sail artisan migrate
./vendor/bin/sail test
./vendor/bin/sail shell          # Access the container shell
```

### Common Commands (Windows CMD / PowerShell)

On Windows CMD the `./vendor/bin/sail` script is not directly executable. Commands are run against the active Docker container:

```cmd
docker exec -it rastro-backend-laravel.test-1 php artisan migrate
docker exec -it rastro-backend-laravel.test-1 php artisan test
docker exec -it rastro-backend-laravel.test-1 bash
```

---

## Database

| Environment | Engine | Configuration |
|---|---|---|
| Local | PostgreSQL 16 via Docker Sail | `DB_*` variables in `.env` pointing to the `pgsql` container service |
| Production | Neon (serverless PostgreSQL) | `DB_*` variables configured in the Render dashboard |

To connect to the Neon production database remotely (read-only or admin), use the credentials available in the Neon dashboard with any PostgreSQL-compatible client (pgAdmin, DBeaver, psql).

---

## Deployment on Render

The service is configured as a **Web Service with Dockerfile**. Render builds the image directly from the Git repository using the `Dockerfile` at the project root.

**Base image:** `php:8.5-cli` with `pdo` and `pdo_pgsql` extensions. Built with multi-stage Composer (`--no-dev --optimize-autoloader`).

### Commands Configured in Render

| Stage | Command |
|---|---|
| Build | *(empty — Render uses the `RUN` steps in the Dockerfile)* |
| Pre-Deploy (Release) | `php artisan migrate --force && php artisan optimize` |
| Start | `php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT` |

Production environment variables (including Neon's `DATABASE_URL` and `APP_KEY`) are configured in the Render dashboard, not in the repository.

---

## Verification Checklist (before merging or deploying)

Run in order. All must pass without errors.

```bash
# 1. Linter
./vendor/bin/sail shell -c "./vendor/bin/pint --test"

# 2. Tests
./vendor/bin/sail test

# 3. Security audit
./vendor/bin/sail shell -c "composer audit"

# 4. Cache optimization
./vendor/bin/sail artisan optimize
```

On Windows CMD:

```cmd
docker exec -it rastro-backend-laravel.test-1 ./vendor/bin/pint --test
docker exec -it rastro-backend-laravel.test-1 php artisan test
docker exec -it rastro-backend-laravel.test-1 composer audit
docker exec -it rastro-backend-laravel.test-1 php artisan optimize
```

---

## License

MIT — see [LICENSE](https://opensource.org/licenses/MIT)