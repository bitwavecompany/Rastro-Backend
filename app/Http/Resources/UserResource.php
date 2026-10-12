<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'plan' => $this->plan?->value ?? $this->plan,
            'role' => $this->role?->value ?? $this->role,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'payment_bank' => $this->payment_bank,
            'payment_receipt' => $this->payment_receipt,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
