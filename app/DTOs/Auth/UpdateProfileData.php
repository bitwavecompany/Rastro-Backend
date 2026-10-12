<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class UpdateProfileData
{
    public function __construct(
        public string $name
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name']
        );
    }
}
