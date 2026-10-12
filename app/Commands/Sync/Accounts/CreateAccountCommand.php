<?php

declare(strict_types=1);

namespace App\Commands\Sync\Accounts;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\AccountType;
use App\Enums\UserPlan;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateAccountCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if ($user->plan === UserPlan::Free && $user->accounts()->whereNull('deleted_at')->count() >= 3) {
            throw new Exception(ApiMessage::LIMIT_ACCOUNTS, HttpCode::FORBIDDEN);
        }

        $data = $this->payload;
        if (isset($data['initial_balance']) && ! isset($data['amount'])) {
            $data['amount'] = $data['initial_balance'];
        }

        $validator = Validator::make($data, [
            'id' => ['required', 'uuid'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts', 'name')->where('user_id', $user->id)->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::enum(AccountType::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], [
            'name.unique' => ApiMessage::ACCOUNT_NAME_DUPLICATE,
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), HttpCode::UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $validated['is_default'] = false;
        $validated['currency'] = $validated['currency'] ?? 'USD';

        $user->accounts()->create($validated);
    }
}
