<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Admin\TogglePlanData;
use App\Enums\PaymentMethod;
use App\Enums\UserPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminTogglePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(UserPlan::class)],
            'payment_method' => ['nullable', 'required_if:plan,premium', Rule::enum(PaymentMethod::class)],
            'payment_bank' => ['nullable', 'string', 'max:255'],
            'payment_receipt' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDto(): TogglePlanData
    {
        $planVal = $this->validated('plan');
        $plan = $planVal instanceof UserPlan ? $planVal : UserPlan::from($planVal);

        $methodVal = $this->validated('payment_method');
        $method = $methodVal ? ($methodVal instanceof PaymentMethod ? $methodVal : PaymentMethod::tryFrom($methodVal)) : null;

        return new TogglePlanData(
            plan: $plan,
            paymentMethod: $method,
            paymentBank: $this->validated('payment_bank'),
            paymentReceipt: $this->validated('payment_receipt'),
        );
    }
}
