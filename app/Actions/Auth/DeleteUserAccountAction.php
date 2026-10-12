<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Constants\ApiMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DeleteUserAccountAction
{
    public function handle(User $user): void
    {
        if ($user->isAdmin()) {
            throw new AccessDeniedHttpException(ApiMessage::FORBIDDEN);
        }

        DB::transaction(function () use ($user): void {
            // Borrado físico permanente en cascada dentro de una transacción atómica (PRD 6.2 y 10.6.2)
            $user->transactions()->forceDelete();
            $user->accounts()->forceDelete();
            $user->sessions()->delete();
            $user->tokens()->delete();
            $user->premiumRequests()->delete();
            $user->forceDelete();
        });
    }
}
