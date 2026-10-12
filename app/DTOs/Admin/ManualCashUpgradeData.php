<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

final readonly class ManualCashUpgradeData
{
    public function __construct(
        public string $paymentNotes
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paymentNotes: (string) $data['payment_notes']
        );
    }
}
