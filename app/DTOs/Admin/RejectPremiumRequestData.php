<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

final readonly class RejectPremiumRequestData
{
    public function __construct(
        public string $rejectionReason
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rejectionReason: (string) $data['rejection_reason']
        );
    }
}
