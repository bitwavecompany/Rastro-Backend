<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\User;
use Carbon\Carbon;

final class ProcessSyncPullAction
{
    /**
     * @return array{server_time: string, accounts: mixed, transactions: mixed}
     */
    public function handle(User $user, ?string $since = null): array
    {
        $sinceDate = null;
        if (! empty($since)) {
            try {
                $sinceDate = Carbon::parse($since);
            } catch (\Throwable) {
                $sinceDate = null;
            }
        }

        $accountsQuery = $user->accounts()->withTrashed();
        if ($sinceDate !== null) {
            $accountsQuery->where('updated_at', '>=', $sinceDate);
        }
        $accounts = $accountsQuery->latest('updated_at')->get();

        $transactionsQuery = $user->transactions()
            ->withTrashed()
            ->forUserPlan($user->plan);

        if ($sinceDate !== null) {
            $transactionsQuery->where('updated_at', '>=', $sinceDate);
        }
        $transactions = $transactionsQuery->latest('updated_at')->get();

        return [
            'server_time' => now()->toIso8601String(),
            'accounts' => AccountResource::collection($accounts),
            'transactions' => TransactionResource::collection($transactions),
        ];
    }
}
