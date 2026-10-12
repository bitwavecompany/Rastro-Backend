<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PremiumRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status?->value ?? $this->status,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'origin_bank' => $this->origin_bank,
            'transfer_receipt' => $this->transfer_receipt,
            'payment_notes' => $this->payment_notes,
            'rejection_reason' => $this->rejection_reason,
            'payment_verified_by' => $this->payment_verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
