<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserSession;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutUserAction
{
    public function handle(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            UserSession::where('access_token_id', $token->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $token->delete();
        }
    }
}
