<?php

declare(strict_types=1);

namespace App\Commands\Sync\Accounts;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\DeactivationReason;
use App\Models\User;
use Exception;

class DeleteAccountCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }
        $account = $user->accounts()->findOrFail($this->payload['id']);

        if ($account->is_default) {
            throw new Exception(ApiMessage::ACCOUNT_DEFAULT_PROTECTED, HttpCode::FORBIDDEN);
        }

        $reason = $this->payload['deactivation_reason'] ?? $this->payload['deactivationReason'] ?? 'user_action';
        $account->deactivation_reason = ($reason === 'plan_downgrade')
            ? DeactivationReason::PlanDowngrade
            : DeactivationReason::UserAction;
        $account->save();

        $account->delete();
    }
}
