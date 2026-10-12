<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Auth\RefreshTokenData;
use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }

    public function toDto(): RefreshTokenData
    {
        return new RefreshTokenData(
            refreshToken: (string) $this->validated('refresh_token')
        );
    }
}
