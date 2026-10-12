<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\ApiMessage;
use App\DTOs\Auth\LoginData;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => ApiMessage::VALIDATION_EMAIL_REQUIRED,
            'email.email' => ApiMessage::VALIDATION_EMAIL_INVALID,
            'password.required' => ApiMessage::VALIDATION_PASSWORD_REQUIRED,
        ];
    }

    public function toDto(): LoginData
    {
        return new LoginData(
            email: (string) $this->validated('email'),
            password: (string) $this->validated('password'),
            deviceName: $this->validated('device_name'),
            force: (bool) $this->boolean('force'),
        );
    }
}
