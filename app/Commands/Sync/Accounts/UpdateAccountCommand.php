<?php

declare(strict_types=1);

namespace App\Commands\Sync\Accounts;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateAccountCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        $account = $user->accounts()->findOrFail($this->payload['id']);

        if ($account->is_default) {
            if (isset($this->payload['type']) && $this->payload['type'] !== $account->type->value) {
                throw new Exception(ApiMessage::ACCOUNT_DEFAULT_PROTECTED, HttpCode::FORBIDDEN);
            }
            if (isset($this->payload['name']) && $this->payload['name'] !== $account->name) {
                throw new Exception(ApiMessage::ACCOUNT_DEFAULT_PROTECTED, HttpCode::FORBIDDEN);
            }
        }

        $validator = Validator::make($this->payload, [
            'id' => ['required', 'uuid'],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('accounts', 'name')
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->ignore($account->id),
            ],
            'type' => ['sometimes', Rule::enum(AccountType::class)],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'initial_balance' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => ApiMessage::ACCOUNT_NAME_DUPLICATE,
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), HttpCode::UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        if (isset($validated['type']) && $validated['type'] !== $account->type->value) {
            $newPaymentMethod = ($validated['type'] === AccountType::Bank->value)
                ? PaymentMethod::Transfer
                : PaymentMethod::Cash;

            // Regla 5: Cascada a todos los movimientos de la cuenta (activos y desactivados)
            if ($account->transactions()->withTrashed()->exists()) {
                $account->transactions()->withTrashed()->update([
                    'payment_method' => $newPaymentMethod->value,
                ]);
            }
        }

        unset($validated['id'], $validated['is_default']);
        $account->update($validated);
    }
}
