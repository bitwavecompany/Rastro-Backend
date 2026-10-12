<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Contracts\SyncCommandInterface;
use App\Models\User;

class PurgeAllTransactionsCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload = []) {}

    public function execute(User $user): void
    {
        $user->transactions()->withTrashed()->forceDelete();
    }
}
