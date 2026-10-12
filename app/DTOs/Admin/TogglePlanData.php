<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

use App\Enums\PaymentMethod;
use App\Enums\UserPlan;

final readonly class TogglePlanData
{
    public function __construct(
        public UserPlan $plan,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $paymentBank = null,
        public ?string $paymentReceipt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            plan: $data['plan'] instanceof UserPlan ? $data['plan'] : UserPlan::from($data['plan']),
            paymentMethod: isset($data['payment_method'])
                ? ($data['payment_method'] instanceof PaymentMethod ? $data['payment_method'] : PaymentMethod::tryFrom($data['payment_method']))
                : null,
            paymentBank: isset($data['payment_bank']) ? (string) $data['payment_bank'] : null,
            paymentReceipt: isset($data['payment_receipt']) ? (string) $data['payment_receipt'] : null,
        );
    }
}
