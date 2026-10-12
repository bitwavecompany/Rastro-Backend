<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Admin\RejectPremiumRequestData;
use Illuminate\Foundation\Http\FormRequest;

class AdminRejectPremiumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function toDto(): RejectPremiumRequestData
    {
        return new RejectPremiumRequestData(
            rejectionReason: (string) $this->validated('rejection_reason')
        );
    }
}
