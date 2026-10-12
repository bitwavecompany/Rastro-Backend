<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteUserAccountAction
{
    public function handle(User $user): void
    {
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
