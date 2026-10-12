# Rastro — Backend Data Model (Laravel / PHP)

> **Date:** October 2026  
> **Status:** Approved / In Implementation  
> **Canonical Equivalent:** [`data-model-frontend.md`](../../rastro-frontend/_context/data-model-frontend.md) (Frontend TypeScript / Vue)  
> **Backend Stack:** Laravel 11/12 — PHP 8.3+ — PostgreSQL 16  

This document defines the migrations, Eloquent models, types, and canonical constraints for the Rastro backend. It acts as the exact counterpart to the mobile client's `data-model.md` to ensure a unified data contract with no divergences.

**Business rule references:** [`business-rules.md`](./business-rules.md) and [`prd.md`](./prd.md)

---

## 1. Architecture Rule: Naming Uniqueness and SSOT

Every concept in the system has a **single field name**, used identically across all layers: TypeScript interfaces, local database (Dexie/IndexedDB), REST API payloads, and PostgreSQL database columns in Laravel.

- **Golden rule:** It is strictly forbidden to rename, abbreviate, or introduce synonyms in backend columns. If the frontend field is called `deleted_at`, the column is called `deleted_at`. If it is called `payment_method`, the column is `payment_method`.
- **Identifiers:** All tables use **UUIDv7** as the primary key. Integer auto-increment is not used to ensure decentralized ID generation in offline mode without collisions.
- **Account balance rule:** The database column is exclusively `initial_balance`. The `amount` field **is not stored** in the table; it is dynamically calculated at query time as a computed attribute (`$appends = ['amount']`).

---

## 2. Table of Canonical Fields and Equivalent SQL Types

| Entity | Canonical Field | TypeScript Type | SQL Type (Laravel Blueprint) | Constraints / Default | Business Semantics |
|---|---|---|---|---|---|
| **User** | `id` | `string` | `$table->uuid('id')->primary()` | Primary Key | UUIDv7 Identifier |
| | `email` | `string \| null` | `$table->string('email')->nullable()->unique()` | Nullable, Unique | Unique email; null for guest users |
| | `name` | `string \| null` | `$table->string('name')->nullable()` | Nullable | Visible name in the app |
| | `plan` | `PlanType` | `$table->string('plan')->default('free')` | 'guest', 'free', 'premium' | User's active plan |
| | `subscription_expires_at` | `string \| null` | `$table->timestamp('subscription_expires_at')->nullable()` | Nullable | Reserved for future billing |
| | `created_at` | `string` | `$table->timestamp('created_at')` | Immutable | Registration date |
| | `updated_at` | `string` | `$table->timestamp('updated_at')` | Automatic | Last update date |
| **Account** | `id` | `string` | `$table->uuid('id')->primary()` | Primary Key | UUIDv7 Identifier |
| | `user_id` | `string` | `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()` | Foreign Key, Indexed | Account owner |
| | `name` | `string` | `$table->string('name')` | Mandatory | Unique name per user (among active) |
| | `type` | `AccountType` | `$table->string('type')` | 'cash', 'bank' | Account type (Rule 1) |
| | `initial_balance` | `number` | `$table->decimal('initial_balance', 12, 2)->default(0)` | Min 0.00, Default 0.00 | Physically recorded initial balance |
| | `amount` | `number` | *Not a column* | `$appends` Accessor | **Calculated at query time:** `initial_balance + incomes - expenses` |
| | `description` | `string \| null` | `$table->text('description')->nullable()` | Nullable | Additional notes |
| | `currency` | `string` | `$table->string('currency', 3)->default('USD')` | Default 'USD' | Reserved for future use |
| | `is_default` | `boolean` | `$table->boolean('is_default')->default(false)` | Default false | `true` only for "Cash" (immutable) |
| | `deactivation_reason` | `DeactivationReason` | `$table->string('deactivation_reason')->nullable()` | 'user_action', 'plan_downgrade' | Deactivation reason (Rule 17) |
| | `deleted_at` | `string \| null` | `$table->softDeletes()` | Nullable, Indexed | Soft Delete (deactivated account) |
| | `created_at` | `string` | `$table->timestamp('created_at')` | Immutable | Creation date |
| | `updated_at` | `string` | `$table->timestamp('updated_at')` | Automatic | Modification date |
| **Transaction** | `id` | `string` | `$table->uuid('id')->primary()` | Primary Key | UUIDv7 Identifier |
| | `user_id` | `string` | `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()` | Foreign Key, Indexed | Transaction owner |
| | `account_id` | `string` | `$table->foreignUuid('account_id')->constrained()->cascadeOnDelete()` | Foreign Key, Indexed | Associated account |
| | `type` | `TransactionType` | `$table->string('type')` | 'income', 'expense' | Transaction nature |
| | `amount` | `number` | `$table->decimal('amount', 12, 2)` | Min 0.01 | Positive transaction amount |
| | `reason` | `string` | `$table->string('reason')` | Max 255 | Title or main reason |
| | `description` | `string \| null` | `$table->text('description')->nullable()` | Nullable | Notes or relocation traceability (Rule 16) |
| | `payment_method` | `PaymentMethod` | `$table->string('payment_method')` | 'cash', 'transfer' | Consistent with `account.type` (Rule 6) |
| | `date` | `string` | `$table->date('date')` | YYYY-MM-DD | Visible transaction date |
| | `category` | `string \| null` | `$table->string('category')->nullable()` | Default null | Reserved for future use |
| | `tags` | `string[]` | `$table->json('tags')->default('[]')` | Default '[]' | Descriptive tags (JSON) |
| | `transfer_id` | `string \| null` | `$table->uuid('transfer_id')->nullable()` | Nullable, Indexed | Transfer link between accounts |
| | `deletion_reason` | `DeletionReason` | `$table->string('deletion_reason')->nullable()` | Nullable | Deactivation / relocation reason |
| | `deleted_at` | `string \| null` | `$table->softDeletes()` | Nullable, Indexed | Transaction Soft Delete |
| | `created_at` | `string` | `$table->timestamp('created_at')` | Immutable | Assigned by the server |
| | `updated_at` | `string` | `$table->timestamp('updated_at')` | Automatic | Automatically updated |
| **OutboxEntry** | `id` | `string` | `$table->uuid('id')->primary()` | Primary Key | UUIDv7 Identifier |
| | `user_id` | `string` | `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()` | Foreign Key, Indexed | Operation owner |
| | `operation` | `OutboxOperation` | `$table->string('operation')` | 'create', 'update', 'delete', 'restore', 'hard-delete', 'purge-account', 'purge-all', 'relocate' | Synced operation |
| | `entity` | `OutboxEntity` | `$table->string('entity')` | 'accounts', 'transactions' | Affected entity |
| | `payload` | `Record<string, unknown>` | `$table->json('payload')` | JSON | Submitted entity snapshot |
| | `status` | `OutboxStatus` | `$table->string('status')->default('pending')` | 'pending', 'synced', 'failed' | Synchronization queue status |
| | `failure_reason` | `string \| null` | `$table->text('failure_reason')->nullable()` | Nullable | Failure reason for Dead Letter Queue |
| | `created_at` | `string` | `$table->timestamp('created_at')` | Immutable | Chronological processing order |
| | `synced_at` | `string \| null` | `$table->timestamp('synced_at')->nullable()` | Nullable | Server confirmation date |
| **PremiumRequest** | `id` | `string` | `$table->uuid('id')->primary()` | Primary Key | UUIDv7 Identifier |
| | `user_id` | `string` | `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()` | Foreign Key, Indexed | Requesting user |
| | `status` | `PremiumRequestStatus` | `$table->string('status')->default('pending_verification')` | 'pending_verification', 'approved', 'rejected' | Request status |
| | `payment_method` | `PaymentMethod` | `$table->string('payment_method')` | 'cash', 'transfer' | Upgrade payment method |
| | `origin_bank` | `string \| null` | `$table->string('origin_bank')->nullable()` | Nullable | Originating bank (transfer) |
| | `transfer_receipt` | `string \| null` | `$table->string('transfer_receipt')->nullable()` | Nullable | Bank receipt number |
| | `payment_notes` | `string \| null` | `$table->text('payment_notes')->nullable()` | Nullable | Admin notes (cash payment) |
| | `rejection_reason` | `string \| null` | `$table->text('rejection_reason')->nullable()` | Nullable | Request rejection reason |
| | `payment_verified_by` | `string \| null` | `$table->foreignUuid('payment_verified_by')->nullable()->constrained('users')->nullOnDelete()` | Foreign Key, Nullable | Auditing admin ID |
| | `verified_at` | `string \| null` | `$table->timestamp('verified_at')->nullable()` | Nullable | Administrative resolution date |
| | `created_at` | `string` | `$table->timestamp('created_at')` | Immutable | Submission date |
| | `updated_at` | `string` | `$table->timestamp('updated_at')` | Automatic | Update date |
| **UserSession** | `id` | `string` | `$table->uuid('id')->primary()` | Primary Key | UUIDv7 Identifier |
| | `user_id` | `string` | `$table->foreignUuid('user_id')->constrained()->cascadeOnDelete()` | Foreign Key, Indexed | Session owner user |
| | `device_name` | `string \| null` | `$table->string('device_name')->nullable()` | Nullable | Device identifier or model |
| | `refresh_token_hash` | `string` | `$table->string('refresh_token_hash', 64)->index()` | SHA-256 Hash | Cryptographic token hash |
| | `ip_address` | `string \| null` | `$table->string('ip_address', 45)->nullable()` | Nullable (IPv4/IPv6) | Connection IP address |
| | `expires_at` | `string` | `$table->timestamp('expires_at')->index()` | Mandatory | Expiration date (30 days) |
| | `revoked_at` | `string \| null` | `$table->timestamp('revoked_at')->nullable()->index()` | Nullable | Remote revocation mark |
| | `created_at` | `string` | `$table->timestamp('created_at')` | Immutable | Session issuance |
| | `updated_at` | `string` | `$table->timestamp('updated_at')` | Automatic | Last refresh |

---

## 3. Canonical Types in PHP 8.3+ (Backed Enums)

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanType: string
{
    case Guest = 'guest';
    case Free = 'free';
    case Premium = 'premium';
}

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
}

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
}

enum PremiumRequestStatus: string
{
    case PendingVerification = 'pending_verification';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

enum OutboxOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case HardDelete = 'hard-delete';
    case PurgeAccount = 'purge-account';
    case PurgeAll = 'purge-all';
    case Relocate = 'relocate';
}

enum OutboxEntity: string
{
    case Accounts = 'accounts';
    case Transactions = 'transactions';
}

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}

enum DeactivationReason: string
{
    case UserAction = 'user_action';
    case PlanDowngrade = 'plan_downgrade';
}
```

---

## 4. Canonical Laravel Migrations

### 4.1 Migration: `create_users_table`
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->string('plan')->default('free')->index();
            $table->string('role')->default('client')->index();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

---

### 4.2 Migration: `create_accounts_table`
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // 'cash' | 'bank'
            $table->decimal('initial_balance', 12, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_default')->default(false);
            $table->string('deactivation_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for business query optimization
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```
> ⚠️ **Critical Rule:** The `amount` column **does not exist** in this table. The current balance is a computed property at query time to guarantee that balance mismatches due to concurrent writes never occur.

---

### 4.3 Migration: `create_transactions_table`
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'income' | 'expense'
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('payment_method'); // 'cash' | 'transfer'
            $table->date('date');
            $table->string('category')->nullable();
            $table->json('tags')->default('[]');
            $table->uuid('transfer_id')->nullable()->index();
            $table->string('deletion_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // High-performance indexes for plan and date filtering
            $table->index(['user_id', 'date']);
            $table->index(['account_id', 'deleted_at']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

---

### 4.4 Migration: `create_outbox_entries_table`
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation'); // 'create', 'update', 'delete', 'restore', 'hard-delete', 'purge-account', 'purge-all', 'relocate'
            $table->string('entity');    // 'accounts' | 'transactions'
            $table->json('payload');
            $table->string('status')->default('pending')->index(); // 'pending' | 'synced' | 'failed'
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at')->index();
            $table->timestamp('synced_at')->nullable();

            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_entries');
    }
};
```

---

### 4.5 Migration: `create_premium_requests_table`
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending_verification')->index();
            $table->string('payment_method'); // 'cash' | 'transfer'
            $table->string('origin_bank')->nullable();
            $table->string('transfer_receipt')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('payment_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_requests');
    }
};
```

---

### 4.6 Migration: `create_user_sessions_table`
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name')->nullable();
            $table->string('refresh_token_hash', 64)->index();
            $table->unsignedBigInteger('access_token_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
```

---

## 5. Canonical Eloquent Models

### 5.1 `User.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    protected $fillable = [
        'id',
        'email',
        'name',
        'password',
        'plan',
        'role',
        'subscription_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'plan' => PlanType::class,
            'role' => UserRole::class,
            'subscription_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function outboxEntries(): HasMany
    {
        return $this->hasMany(OutboxEntry::class);
    }

    public function premiumRequests(): HasMany
    {
        return $this->hasMany(PremiumRequest::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }
}
```

---

### 5.2 `Account.php` (With dynamic calculation for `amount`)
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\DeactivationReason;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'type',
        'initial_balance',
        'description',
        'currency',
        'is_default',
        'deactivation_reason',
    ];

    /**
     * The current balance 'amount' is calculated at query time.
     * It is not stored as a physical column in the table.
     */
    protected $appends = [
        'amount',
    ];

    protected static function booted(): void
    {
        // Rule 18: Deactivating an account cascade-deactivates all its transactions
        static::deleting(function (Account $account) {
            if (! $account->isForceDeleting()) {
                $account->transactions()->whereNull('deleted_at')->update([
                    'deletion_reason' => 'cascade_account_deactivation',
                    'deleted_at' => now(),
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => 'float',
            'is_default' => 'boolean',
            'deactivation_reason' => DeactivationReason::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Canonical accessor for the calculated balance:
     * amount = initial_balance + active incomes - active expenses
     */
    public function getAmountAttribute(): float
    {
        $totals = $this->transactions()
            ->whereNull('deleted_at')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
            ")
            ->first();

        $income = (float) ($totals->total_income ?? 0);
        $expense = (float) ($totals->total_expense ?? 0);

        return (float) ($this->initial_balance + $income - $expense);
    }

    public function scopeActiveOnly(Builder $query, bool $activeOnly = true): Builder
    {
        return $activeOnly ? $query->whereNull('deleted_at') : $query->withTrashed();
    }

    public function scopeOfType(Builder $query, AccountType|string|null $type): Builder
    {
        if (empty($type)) {
            return $query;
        }

        $typeValue = $type instanceof AccountType ? $type->value : $type;

        return $query->where('type', $typeValue);
    }
}
```

---

### 5.3 `Transaction.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PlanType;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'account_id',
        'type',
        'amount',
        'reason',
        'description',
        'payment_method',
        'date',
        'category',
        'tags',
        'transfer_id',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'float',
            'payment_method' => PaymentMethod::class,
            'date' => 'date',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForUserPlan(Builder $query, PlanType $plan): Builder
    {
        if ($plan === PlanType::Premium) {
            return $query;
        }

        $cutoff = now()->subMonths(6);

        return $query->where(function (Builder $q) use ($cutoff) {
            $q->where('date', '>=', $cutoff)
                ->orWhere(function (Builder $sub) use ($cutoff) {
                    $sub->whereNull('date')
                        ->where('created_at', '>=', $cutoff);
                });
        });
    }

    public function scopeActiveOnly(Builder $query, bool $activeOnly = true): Builder
    {
        return $activeOnly ? $query->whereNull('deleted_at') : $query->withTrashed();
    }

    public function scopeOfType(Builder $query, TransactionType|string|null $type): Builder
    {
        if (empty($type)) {
            return $query;
        }

        $val = $type instanceof TransactionType ? $type->value : $type;

        return $query->where('type', $val);
    }

    public function scopeForAccount(Builder $query, ?string $accountId): Builder
    {
        return ! empty($accountId) ? $query->where('account_id', $accountId) : $query;
    }
}
```

---

### 5.4 `OutboxEntry.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutboxEntity;
use App\Enums\OutboxOperation;
use App\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboxEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'operation',
        'entity',
        'payload',
        'status',
        'failure_reason',
        'created_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'operation' => OutboxOperation::class,
            'entity' => OutboxEntity::class,
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'created_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

### 5.5 `PremiumRequest.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PremiumRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PremiumRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'payment_method',
        'origin_bank',
        'transfer_receipt',
        'payment_notes',
        'rejection_reason',
        'payment_verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PremiumRequestStatus::class,
            'payment_method' => PaymentMethod::class,
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }
}
```

---

### 5.6 `UserSession.php`
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'device_name',
        'refresh_token_hash',
        'access_token_id',
        'ip_address',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }
}
```

---

## 6. Historical Divergences Mapping (EXECUTED AND RESOLVED)

> **Status:** Resolved and applied in production / database.
> **Applied migrations:**
> 1. `2026_10_11_000003_rename_legacy_columns_in_transactions_table.php` (`kind` → `type`, `paymentType` → `payment_method`, `deleteReason` → `deletion_reason`)
> 2. `2026_10_11_000004_rename_amount_to_initial_balance_in_accounts_table.php` (`amount` → `initial_balance`)

The columns in PostgreSQL now match 1:1 with the canonical `snake_case` contract from `data-model.md`. Translation layers in `TransactionResource` and `AccountResource` were removed:
- `TransactionResource` directly returns `type`, `payment_method`, `deletion_reason` and formats `date` with `toDateString()`.
- `AccountResource` returns `initial_balance` (physical column) and `amount` (computed accessor for the dynamic balance via `$appends = ['amount']`).

With this schema and these applied migrations, **Frontend and Backend are formally bound under a 100% identical and immutable data contract**.