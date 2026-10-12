<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\DeactivationReason;
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
        'amount',
        'description',
        'currency',
        'is_default',
        'deactivation_reason',
    ];

    protected $appends = [
        'amount',
    ];

    protected static function booted(): void
    {
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

    public function calculateCurrentBalance(): float
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

    public function getAmountAttribute(): float
    {
        return $this->calculateCurrentBalance();
    }

    public function setAmountAttribute($value): void
    {
        $this->attributes['initial_balance'] = $value;
    }

    public function getCurrentBalanceAttribute(): float
    {
        return $this->calculateCurrentBalance();
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
