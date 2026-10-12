<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\ApiMessage;
use App\DTOs\Auth\UpdateProfileData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => ApiMessage::VALIDATION_NAME_OR_ALIAS_REQUIRED,
            'name.max' => ApiMessage::VALIDATION_NAME_MAX,
        ];
    }

    public function toDto(): UpdateProfileData
    {
        return new UpdateProfileData(
            name: (string) $this->validated('name')
        );
    }
}
