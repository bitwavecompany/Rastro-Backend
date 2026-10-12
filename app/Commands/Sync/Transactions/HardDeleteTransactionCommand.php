<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Models\Transaction;
use App\Models\User;
use Exception;

class HardDeleteTransactionCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        /** @var Transaction $transaction */
        $transaction = $user->transactions()->withTrashed()->findOrFail($this->payload['id']);

        if ($transaction->deleted_at === null) {
            throw new Exception(ApiMessage::HARD_DELETE_REQUIRES_SOFT_DELETE, HttpCode::UNPROCESSABLE_ENTITY);
        }

        $transaction->forceDelete();
    }
}
