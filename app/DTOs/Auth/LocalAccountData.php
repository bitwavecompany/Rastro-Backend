<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Enums\AccountType;

readonly class LocalAccountData
{
    public function __construct(
        public string $id,
        public string $name,
        public AccountType $type,
        public float $amount,
        public ?string $description = null,
        public bool $is_default = false,
        public string $currency = 'USD',
    ) {}
}
