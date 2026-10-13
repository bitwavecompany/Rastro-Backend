<?php

declare(strict_types=1);

namespace App\Commands\Sync\Transactions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
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

        $transaction = $user->transactions()->findOrFail($this->payload['id']);

        $data = $this->payload;
        if (isset($data['type']) && ! isset($data['kind'])) {
            $data['kind'] = $data['type'];
        }
        if (isset($data['payment_method']) && ! isset($data['paymentType'])) {
            $data['paymentType'] = $data['payment_method'];
        }

        if (isset($data['category']) && is_string($data['category'])) {
            $data['category'] = strtolower(trim($data['category']));
        }

        $effectiveKind = $data['kind'] ?? $transaction->type?->value ?? $transaction->kind?->value;
        $isExpense = ($effectiveKind === TransactionKind::Expense->value);

        $categoryRules = ['nullable'];
        if ($isExpense) {
            if (array_key_exists('category', $data) || empty($transaction->category)) {
                $categoryRules = ['required', Rule::enum(TransactionCategory::class)];
            }
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
            'category' => $categoryRules,
            'tags' => ['nullable', 'array'],
            'transfer_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first(), HttpCode::UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        if (! $isExpense && array_key_exists('kind', $validated)) {
            $validated['category'] = null;
        }

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
