<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Contracts\SyncCommandInterface;
use App\Models\SyncTombstone;
use App\Models\User;

class PurgeAllTransactionsCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload = []) {}

    public function execute(User $user): void
    {
        $now = now();
        $txIds = $user->transactions()->withTrashed()->pluck('id');

        if ($txIds->isNotEmpty()) {
            $tombstones = $txIds->map(fn ($id): array => [
                'user_id' => $user->id,
                'entity' => 'transactions',
                'entity_id' => (string) $id,
                'deleted_at' => $now,
            ])->all();

            SyncTombstone::insert($tombstones);
        }

        $user->transactions()->withTrashed()->forceDelete();
    }
}
