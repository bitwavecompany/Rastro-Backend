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
