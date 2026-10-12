<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Enums\TransactionType;
use App\Enums\UserPlan;
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
        'kind',
        'amount',
        'reason',
        'description',
        'payment_method',
        'paymentType',
        'date',
        'category',
        'tags',
        'deletion_reason',
        'deleteReason',
        'transfer_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'payment_method' => PaymentMethod::class,
            'deletion_reason' => 'string',
            'amount' => 'float',
            'date' => 'datetime',
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

    public function setKindAttribute($value): void
    {
        $this->attributes['type'] = $value instanceof \BackedEnum ? $value->value : $value;
    }

    public function getKindAttribute(): mixed
    {
        return $this->type;
    }

    public function setPaymentTypeAttribute($value): void
    {
        $this->attributes['payment_method'] = $value instanceof \BackedEnum ? $value->value : $value;
    }

    public function getPaymentTypeAttribute(): mixed
    {
        return $this->payment_method instanceof \BackedEnum ? $this->payment_method->value : $this->payment_method;
    }

    public function setDeleteReasonAttribute($value): void
    {
        $this->attributes['deletion_reason'] = $value;
    }

    public function getDeleteReasonAttribute(): mixed
    {
        return $this->deletion_reason;
    }

    public function scopeForUserPlan(Builder $query, UserPlan $plan): Builder
    {
        if ($plan === UserPlan::Premium) {
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

    public function scopeOfKind(Builder $query, TransactionKind|TransactionType|string|null $kind): Builder
    {
        if (empty($kind)) {
            return $query;
        }

        $kindValue = $kind instanceof \BackedEnum ? $kind->value : $kind;

        return $query->where('type', $kindValue);
    }

    public function scopeOfType(Builder $query, TransactionKind|TransactionType|string|null $type): Builder
    {
        return $this->scopeOfKind($query, $type);
    }

    public function scopeForAccount(Builder $query, ?string $accountId): Builder
    {
        if (empty($accountId)) {
            return $query;
        }

        return $query->where('account_id', $accountId);
    }
}
