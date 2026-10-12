<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class RefreshTokenData
{
    public function __construct(
        public string $refreshToken
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            refreshToken: (string) $data['refresh_token']
        );
    }
}
