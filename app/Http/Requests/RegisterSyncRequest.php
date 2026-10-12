<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\ApiMessage;
use App\DTOs\Auth\LocalAccountData;
use App\DTOs\Auth\LocalTransactionData;
use App\DTOs\Auth\RegisterSyncData;
use App\Enums\AccountType;
use App\Enums\TransactionKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],

            'localData' => ['nullable', 'array'],
            'localData.accounts' => ['nullable', 'array'],
            'localData.accounts.*.id' => ['required_with:localData.accounts', 'string'],
            'localData.accounts.*.name' => ['required_with:localData.accounts', 'string', 'max:255'],
            'localData.accounts.*.type' => ['required_with:localData.accounts', Rule::enum(AccountType::class)],
            'localData.accounts.*.amount' => ['nullable', 'numeric', 'min:0'],
            'localData.accounts.*.initial_balance' => ['nullable', 'numeric', 'min:0'],
            'localData.accounts.*.description' => ['nullable', 'string', 'max:255'],
            'localData.accounts.*.is_default' => ['nullable', 'boolean'],
            'localData.accounts.*.currency' => ['nullable', 'string', 'size:3'],

            'localData.transactions' => ['nullable', 'array'],
            'localData.transactions.*.id' => ['required_with:localData.transactions', 'string'],
            'localData.transactions.*.accountId' => ['required_with:localData.transactions', 'string'],
            'localData.transactions.*.kind' => ['required_with:localData.transactions', Rule::enum(TransactionKind::class)],
            'localData.transactions.*.amount' => ['required_with:localData.transactions', 'numeric', 'min:0.01'],
            'localData.transactions.*.reason' => ['required_with:localData.transactions', 'string', 'max:255'],
            'localData.transactions.*.description' => ['nullable', 'string', 'max:255'],
            'localData.transactions.*.paymentType' => ['required_with:localData.transactions', 'string', 'max:255'],
            'localData.transactions.*.deleteReason' => ['nullable', 'string', 'max:255'],
            'localData.transactions.*.deletedAt' => ['nullable', 'date'],
            'localData.transactions.*.date' => ['nullable', 'date'],
            'localData.transactions.*.category' => ['nullable', 'string', 'max:255'],
            'localData.transactions.*.tags' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => ApiMessage::VALIDATION_EMAIL_UNIQUE,
            'email.required' => ApiMessage::VALIDATION_EMAIL_REQUIRED,
            'email.email' => ApiMessage::VALIDATION_EMAIL_INVALID,
            'name.required' => ApiMessage::VALIDATION_NAME_REQUIRED,
            'password.required' => ApiMessage::VALIDATION_PASSWORD_REQUIRED,
            'password.min' => ApiMessage::VALIDATION_PASSWORD_MIN,
        ];
    }

    public function toDto(): RegisterSyncData
    {
        $accountsData = $this->validated('localData.accounts', []);
        $transactionsData = $this->validated('localData.transactions', []);

        $accounts = array_map(function (array $acc) {
            $balance = (float) ($acc['initial_balance'] ?? $acc['amount'] ?? 0);

            return new LocalAccountData(
                id: $acc['id'],
                name: $acc['name'],
                type: AccountType::from($acc['type']),
                amount: $balance,
                description: $acc['description'] ?? null,
                is_default: (bool) ($acc['is_default'] ?? false),
                currency: $acc['currency'] ?? 'USD',
            );
        }, $accountsData);

        $transactions = array_map(function (array $tx) {
            return new LocalTransactionData(
                id: $tx['id'],
                accountId: $tx['accountId'],
                kind: TransactionKind::from($tx['kind']),
                amount: (float) $tx['amount'],
                reason: $tx['reason'],
                description: $tx['description'] ?? null,
                paymentType: $tx['paymentType'],
                deleteReason: $tx['deleteReason'] ?? null,
                deletedAt: $tx['deletedAt'] ?? null,
                date: $tx['date'] ?? null,
                category: $tx['category'] ?? null,
                tags: $tx['tags'] ?? null,
            );
        }, $transactionsData);

        return new RegisterSyncData(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
            accounts: $accounts,
            transactions: $transactions,
        );
    }
}
