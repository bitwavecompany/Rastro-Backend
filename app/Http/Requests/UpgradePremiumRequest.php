<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Auth\UpgradePremiumData;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class UpgradePremiumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Soporte para alias de campos entre PRD y API Contract
        if ($this->has('payment_bank') && ! $this->has('origin_bank')) {
            $this->merge(['origin_bank' => $this->input('payment_bank')]);
        }
        if ($this->has('payment_receipt') && ! $this->has('transfer_receipt')) {
            $this->merge(['transfer_receipt' => $this->input('payment_receipt')]);
        }
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'in:transfer,cash'],
            'origin_bank' => ['required', 'string', 'max:255'],
            'transfer_receipt' => ['required', 'string', 'max:255'],
        ];
    }

    public function toDto(): UpgradePremiumData
    {
        $methodStr = $this->validated('payment_method', 'transfer');
        $method = PaymentMethod::tryFrom($methodStr) ?? PaymentMethod::Transfer;

        return new UpgradePremiumData(
            paymentMethod: $method,
            originBank: (string) $this->validated('origin_bank'),
            transferReceipt: (string) $this->validated('transfer_receipt'),
        );
    }
}
