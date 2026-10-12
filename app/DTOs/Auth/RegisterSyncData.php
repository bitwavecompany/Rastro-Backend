<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

readonly class RegisterSyncData
{
    /**
     * @param  LocalAccountData[]  $accounts
     * @param  LocalTransactionData[]  $transactions
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public array $accounts,
        public array $transactions,
    ) {}
}
