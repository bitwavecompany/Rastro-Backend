<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Models\SyncTombstone;
use App\Models\User;
use Exception;

class PurgeAccountTransactionsCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        $accountId = $this->payload['account_id'] ?? $this->payload['id'] ?? null;

        if (! $accountId) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        $account = $user->accounts()->withTrashed()->findOrFail($accountId);

        $now = now();
        $txIds = $account->transactions()->withTrashed()->pluck('id');

        if ($txIds->isNotEmpty()) {
            $tombstones = $txIds->map(fn ($id): array => [
                'user_id' => $user->id,
                'entity' => 'transactions',
                'entity_id' => (string) $id,
                'deleted_at' => $now,
            ])->all();

            SyncTombstone::insert($tombstones);
        }

        $account->transactions()->withTrashed()->forceDelete();
    }
}
