<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateTransactionCommand implements SyncCommandInterface
{
    public function __construct(private readonly array $payload) {}

    public function execute(User $user): void
    {
        if (! isset($this->payload['id'])) {
            throw new Exception(ApiMessage::MISSING_ID, HttpCode::BAD_REQUEST);
        }

        $data = $this->payload;
        if (isset($data['type']) && ! isset($data['kind'])) {
            $data['kind'] = $data['type'];
        }
        if (isset($data['payment_method']) && ! isset($data['paymentType'])) {
            $data['paymentType'] = $data['payment_method'];
        }

        $validator = Validator::make($data, [
            'id' => ['required', 'uuid'],
            'account_id' => ['sometimes', 'uuid'],
            'kind' => ['sometimes', Rule::enum(TransactionKind::class)],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'reason' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'paymentType' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'date' => ['nullable', 'date'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'transfer_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), HttpCode::UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $transaction = $user->transactions()->findOrFail($validated['id']);

        $targetAccountId = $validated['account_id'] ?? $transaction->account_id;
        $targetPaymentType = $validated['paymentType'] ?? $transaction->paymentType;

        if (isset($validated['account_id']) || isset($validated['paymentType'])) {
            /** @var Account|null $account */
            $account = $user->accounts()->withTrashed()->find($targetAccountId);

            // Regla 6: La cuenta debe existir y estar activa (deleted_at = null)
            if (! $account || $account->trashed()) {
                throw new Exception(ApiMessage::ACCOUNT_INACTIVE, HttpCode::FORBIDDEN);
            }

            // Regla 6: Coherencia de tipo de cuenta y payment_method
            $isCashMatch = ($targetPaymentType === PaymentMethod::Cash->value && $account->type === AccountType::Cash);
            $isTransferMatch = ($targetPaymentType === PaymentMethod::Transfer->value && $account->type === AccountType::Bank);

            if (! $isCashMatch && ! $isTransferMatch) {
                throw new Exception(ApiMessage::ACCOUNT_TYPE_MISMATCH, HttpCode::FORBIDDEN);
            }
        }

        if (isset($validated['date']) && ! empty($validated['date'])) {
            $dateString = trim((string) ($data['date'] ?? ''));
            $parsedDate = Carbon::parse($validated['date']);
            if (strlen($dateString) <= 10) {
                $validated['date'] = $parsedDate->setTime(now()->hour, now()->minute, now()->second);
            } else {
                $validated['date'] = $parsedDate;
            }
        }

        unset($validated['id']);
        $transaction->update($validated);
    }
}
