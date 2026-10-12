<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Enums\TransactionKind;

readonly class LocalTransactionData
{
    public function __construct(
        public string $id,
        public string $accountId,
        public TransactionKind $kind,
        public float $amount,
        public string $reason,
        public ?string $description = null,
        public string $paymentType = 'cash',
        public ?string $deleteReason = null,
        public ?string $deletedAt = null,
        public ?string $date = null,
        public ?string $category = null,
        public ?array $tags = null,
    ) {}
}
