<?php

declare(strict_types=1);

namespace App\Commands\Sync\Accounts;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Models\Account;
use App\Models\SyncTombstone;
use App\Models\User;
use Exception;

class HardDeleteAccountCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        /** @var Account $account */
        $account = $user->accounts()->withTrashed()->findOrFail($this->payload['id']);

        if ($account->is_default) {
            throw new Exception(ApiMessage::ACCOUNT_DEFAULT_PROTECTED, HttpCode::FORBIDDEN);
        }

        $now = now();
        $txIds = $account->transactions()->withTrashed()->pluck('id');

        $tombstones = $txIds->map(fn ($id): array => [
            'user_id' => $user->id,
            'entity' => 'transactions',
            'entity_id' => (string) $id,
            'deleted_at' => $now,
        ])->all();

        $tombstones[] = [
            'user_id' => $user->id,
            'entity' => 'accounts',
            'entity_id' => (string) $account->id,
            'deleted_at' => $now,
        ];

        SyncTombstone::insert($tombstones);

        // Eliminación física irreversible de todos sus registros y de la cuenta (Reglas 6.2 y 20)
        $account->transactions()->withTrashed()->forceDelete();
        $account->forceDelete();
    }
}
