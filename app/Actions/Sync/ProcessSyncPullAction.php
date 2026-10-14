<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\SyncTombstone;
use App\Models\User;
use Carbon\Carbon;

final class ProcessSyncPullAction
{
    /**
     * @return array{
     *     server_time: string,
     *     accounts: mixed,
     *     transactions: mixed,
     *     deleted_account_ids: array<int, string>,
     *     deleted_transaction_ids: array<int, string>
     * }
     */
    public function handle(User $user, ?string $since = null): array
    {
        $sinceDate = null;
        if (! empty($since)) {
            try {
                $sinceDate = Carbon::parse($since)->subSeconds(2);
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

        $deletedAccountIds = [];
        $deletedTransactionIds = [];

        if ($sinceDate !== null) {
            $tombstones = SyncTombstone::where('user_id', $user->id)
                ->where('deleted_at', '>=', $sinceDate)
                ->get();

            /** @var array<int, string> $deletedAccountIds */
            $deletedAccountIds = $tombstones
                ->where('entity', 'accounts')
                ->pluck('entity_id')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();

            /** @var array<int, string> $deletedTransactionIds */
            $deletedTransactionIds = $tombstones
                ->where('entity', 'transactions')
                ->pluck('entity_id')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [
            'server_time' => now()->toIso8601String(),
            'accounts' => AccountResource::collection($accounts),
            'transactions' => TransactionResource::collection($transactions),
            'deleted_account_ids' => $deletedAccountIds,
            'deleted_transaction_ids' => $deletedTransactionIds,
        ];
    }
}
