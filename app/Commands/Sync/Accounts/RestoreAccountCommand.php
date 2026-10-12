<?php

declare(strict_types=1);

namespace App\Commands\Sync\Accounts;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\UserPlan;
use App\Models\User;
use Exception;

class RestoreAccountCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        if ($user->plan === UserPlan::Free && $user->accounts()->whereNull('deleted_at')->count() >= 3) {
            throw new Exception(ApiMessage::LIMIT_ACCOUNTS, HttpCode::FORBIDDEN);
        }

        $account = $user->accounts()->onlyTrashed()->findOrFail($this->payload['id']);
        $account->deactivation_reason = null;
        $account->save();
        $account->restore();
    }
}
