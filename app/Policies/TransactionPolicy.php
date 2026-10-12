<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserPlan;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can export transactions to CSV.
     */
    public function export(User $user): bool
    {
        return $user->plan === UserPlan::Premium;
    }
}
