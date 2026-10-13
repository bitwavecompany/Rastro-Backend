# AI Agent Instructions (Rastro Backend)

This file contains immutable rules to ensure that AI assistance in this repository is consistent, free of architectural errors, and aligned with the system's canonical data contract.

---

## 0. Mandatory Reference Documents

Before performing any task, load and respect these documents in order of priority:

| Priority | Document | Content |
|---|---|---|
| 1 | `_context/prd.md` | Business rules — absolute source of truth |
| 2 | `_context/business-rules.md` | Detailed rules per entity and flow |
| 3 | `_context/data-model-backend.md` | Canonical DB schema, models, enums, and fields |
| 4 | `_context/api_contract.md` | API contract for endpoints, payloads, and responses |
| 5 | `_context/backlog.md` | Requirements backlog and product roadmap |

No code suggestion can contradict what these documents establish. In case of conflict between them, the priority order in the table acts as the tiebreaker.

---

## 1. The PRD as the Absolute Source of Truth

- **Strict typing:** Never invent types that are not in the PRD. Accounts can only be `cash` or `bank`. Transactions can only be `income` or `expense`.
- **Soft Deletes:** All deletions of financial entities (Accounts, Transactions) must be logical (`SoftDeletes`). Using `forceDelete()` is forbidden except in total user deletion (`DELETE /api/auth/me`) and explicit purge operations from the sync catalog (`hard-delete`, `purge-account`, `purge-all`).
- **Default Account Protection:** Every user has a "Cash" account with `is_default = true`. Deleting, deactivating, or changing its type (`cash`) or name is strictly forbidden under any circumstance. However, editing its `initial_balance` and description is allowed so users can record and correct their opening capital without distorting monthly income metrics. Always protect `is_default = true` and `type = 'cash'` before executing any account mutation.

---

## 2. Canonical Naming (Strict snake_case)

All fields — DB columns, Eloquent model attributes, API payloads, and frontend TypeScript types — use `snake_case` identically across all layers. Introducing synonyms, abbreviations, or camelCase variants is strictly forbidden.

Canonical fields that had historical names and **must no longer be used**:

| Correct field | Forbidden historical name |
|---|---|
| `type` (in `Transaction`) | `kind` |
| `payment_method` | `paymentType` |
| `deletion_reason` | `deleteReason` |
| `initial_balance` (column in `accounts`) | `amount` (as a column) |
| `name` (in `User`) | `alias` |

The `amount` field in `Account` is a **computed accessor** (`$appends`), not a physical column. Do not write or read `amount` as a column in the `accounts` table.

---

## 3. Offline-First Architecture (Batch Sync)

- **Zero Chatty CRUD:** Building individual mutation endpoints (POST, PUT, DELETE) for Accounts or Transactions is forbidden.
- Every client mutation is processed through `POST /api/sync/push` using the Outbox pattern.
- Batch operations are processed within a `DB::transaction`. A failure in an individual operation does not cancel the batch — it returns `status: "error"` only for that `opId`.
- The full catalog of supported operations is defined in `_context/api_contract.md` (Section 4.2). Do not implement operations outside of that catalog without updating the contract first.

---

## 4. Architecture Rules and Patterns (Clean Code + GoF)

### Error handling — zero magic strings
Hardcoding strings or codes in controllers or responses is forbidden. Every message comes from `App\Constants\ApiMessage` and every HTTP code from `App\Constants\HttpCode`. If the message does not exist, add it to `ApiMessage` before using it.

### DTO Boundaries
Controllers never send raw arrays to Actions. Always map the Request to a DTO class in `app/DTOs` before passing it.

### Command Pattern in Sync
`ProcessBatchSyncAction` is closed for modification (OCP). To add a new sync operation:
1. Create a new Command in `app/Commands/Sync/` implementing `SyncCommandInterface`.
2. Register it in `SyncCommandFactory`.
3. Update `_context/api_contract.md` with the new operation.

Do not use `if/else` in the sync pipeline to branch operations.

### Strict Typing
`declare(strict_types=1)` is mandatory in every PHP file. All arguments and returns must be typed.

### Enums
Using magic strings for domain types is forbidden. Always use `app/Enums` (`AccountType::Cash`, `TransactionType::Income`, `TransactionCategory::Food`, `OutboxOperation::Relocate`, etc.).

### Actions
Controllers are thin layers: receive request, map to DTO, invoke Action, return Resource. Business logic goes in `app/Actions`.

### API Resources
Never return raw Eloquent models to the client. Always use `app/Http/Resources`. The standard envelope response format is applied via the `ApiResponse` trait (`App\Traits\ApiResponse`).

---

## 5. Environment (Sail and Docker on Windows Host)

### Linux / WSL / Git Bash
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan <command>
./vendor/bin/sail test
./vendor/bin/sail shell
```

### Windows CMD / PowerShell
CMD does not support Unix shell scripts. Execute directly on the active container:
```cmd
docker exec -it rastro-backend-laravel.test-1 php artisan <command>
docker exec -it rastro-backend-laravel.test-1 php artisan test
docker exec -it rastro-backend-laravel.test-1 bash
```

---

## 6. Verification Checklist (run before closing any task)

All steps must pass without errors. On Windows, replace `./vendor/bin/sail shell -c` with `docker exec -it rastro-backend-laravel.test-1`.

```bash
# 1. Linter and code style
./vendor/bin/sail shell -c "./vendor/bin/pint --test"

# 2. Full test suite
./vendor/bin/sail test

# 3. Dependency security audit
./vendor/bin/sail shell -c "composer audit"

# 4. Cache optimization for production
./vendor/bin/sail artisan optimize
```

---

## 7. Repository Skills and Tools

- Always load custom skills in `.agents/skills/` (especially `laravel-patterns`, `php-pro`, `laravel-tdd`) before refactoring or creating new features.
- Use `graphify` (`graphify-out/`) to understand the relationships between components before modifying the architecture.