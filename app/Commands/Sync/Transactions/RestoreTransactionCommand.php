<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Models\Transaction;
use App\Models\User;
use Exception;

class RestoreTransactionCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        /** @var Transaction $transaction */
        $transaction = $user->transactions()->onlyTrashed()->findOrFail($this->payload['id']);

        // Verificar que la cuenta asociada esté activa
        $accountIsActive = $user->accounts()
            ->where('id', $transaction->account_id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $accountIsActive) {
            throw new Exception(ApiMessage::ACCOUNT_INACTIVE, HttpCode::FORBIDDEN);
        }

        $transaction->restore();
    }
}
