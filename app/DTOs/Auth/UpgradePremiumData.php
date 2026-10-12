<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Enums\PaymentMethod;

readonly class UpgradePremiumData
{
    public function __construct(
        public PaymentMethod $paymentMethod,
        public string $originBank,
        public string $transferReceipt,
    ) {}
}
