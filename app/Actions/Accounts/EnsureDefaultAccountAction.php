<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;

final class EnsureDefaultAccountAction
{
    public function handle(User $user): Account
    {
        /** @var Account|null $existingDefault */
        $existingDefault = $user->accounts()->where('is_default', true)->first();
        if ($existingDefault !== null) {
            return $existingDefault;
        }

        return $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 0.0,
            'description' => 'Cuenta principal por defecto',
            'currency' => 'USD',
            'is_default' => true,
        ]);
    }
}
