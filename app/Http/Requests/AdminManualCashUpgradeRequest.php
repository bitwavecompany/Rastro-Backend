<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Admin\ManualCashUpgradeData;
use Illuminate\Foundation\Http\FormRequest;

class AdminManualCashUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_notes' => ['required', 'string', 'max:500'],
        ];
    }

    public function toDto(): ManualCashUpgradeData
    {
        return new ManualCashUpgradeData(
            paymentNotes: (string) $this->validated('payment_notes')
        );
    }
}
