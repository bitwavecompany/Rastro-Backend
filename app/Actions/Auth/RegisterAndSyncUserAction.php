<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Accounts\EnsureDefaultAccountAction;
use App\Constants\ApiMessage;
use App\DTOs\Auth\RegisterSyncData;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class RegisterAndSyncUserAction
{
    public function __construct(
        private readonly EnsureDefaultAccountAction $ensureDefaultAccount = new EnsureDefaultAccountAction
    ) {}

    /**
     * @return array{user: User, token: string, syncedData: array}
     */
    public function handle(RegisterSyncData $data): array
    {
        DB::beginTransaction();

        try {
            /** @var User $user */
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
            ]);

            $accountMapping = [];
            $savedAccounts = [];
            $savedTransactions = [];

            $hasDefault = false;

            if (count($data->accounts) > 0) {
                // Determinar cuál es la cuenta por defecto
                $defaultIndex = null;
                foreach ($data->accounts as $index => $acc) {
                    if ($acc->is_default) {
                        $defaultIndex = $index;
                        break;
                    }
                }
                // Si ninguna viene con is_default, pero una se llama Efectivo
                if ($defaultIndex === null) {
                    foreach ($data->accounts as $index => $acc) {
                        if (strtolower(trim($acc->name)) === 'efectivo') {
                            $defaultIndex = $index;
                            break;
                        }
                    }
                }

                foreach ($data->accounts as $index => $localAccount) {
                    $isDefaultAccount = ($defaultIndex !== null && $index === $defaultIndex);
                    if ($isDefaultAccount) {
                        $hasDefault = true;
                    }

                    $account = $user->accounts()->create([
                        'id' => $localAccount->id,
                        'name' => $localAccount->name,
                        'type' => $localAccount->type,
                        'amount' => $localAccount->amount,
                        'description' => $localAccount->description,
                        'currency' => $localAccount->currency,
                        'is_default' => $isDefaultAccount,
                    ]);

                    $accountMapping[$localAccount->id] = $account->id;
                    $savedAccounts[] = $account;
                }
            }

            // Si el usuario no tenía cuenta por defecto, se crea automáticamente vía SSOT
            if (! $hasDefault) {
                $defaultAccount = $this->ensureDefaultAccount->handle($user);
                $savedAccounts[] = $defaultAccount;
            }

            foreach ($data->transactions as $localTx) {
                $realAccountUuid = $accountMapping[$localTx->accountId] ?? null;

                if ($realAccountUuid) {
                    $tx = $user->transactions()->create([
                        'id' => $localTx->id,
                        'account_id' => $realAccountUuid,
                        'kind' => $localTx->kind,
                        'amount' => $localTx->amount,
                        'reason' => $localTx->reason,
                        'description' => $localTx->description,
                        'paymentType' => $localTx->paymentType,
                        'deleteReason' => $localTx->deleteReason,
                        'date' => $localTx->date ?? now(),
                        'category' => $localTx->category,
                        'tags' => $localTx->tags,
                    ]);

                    if ($localTx->deletedAt !== null) {
                        $tx->deleted_at = $localTx->deletedAt;
                        $tx->save();
                    }
                    $savedTransactions[] = $tx;
                }
            }

            DB::commit();

            $tokenPair = app(TokenService::class)->issueTokens($user);

            return [
                'user' => $user,
                'token' => $tokenPair['token'],
                'access_token' => $tokenPair['access_token'],
                'refresh_token' => $tokenPair['refresh_token'],
                'token_type' => $tokenPair['token_type'],
                'expires_in' => $tokenPair['expires_in'],
                'syncedData' => [
                    'accounts' => $user->accounts()->orderByDesc('created_at')->orderByDesc('id')->get(),
                    'transactions' => $user->transactions()->orderByDesc('date')->orderByDesc('created_at')->orderByDesc('id')->get(),
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'sync' => config('app.debug') ? $e->getMessage() : ApiMessage::SYNC_INTERNAL_ERROR,
            ]);
        }
    }
}
