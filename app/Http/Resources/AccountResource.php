<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DeactivationReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'amount' => (float) $this->amount,
            'initial_balance' => (float) $this->initial_balance,
            'description' => $this->description,
            'currency' => $this->currency ?? 'USD',
            'is_default' => (bool) $this->is_default,
            'deactivation_reason' => $this->deactivation_reason instanceof DeactivationReason ? $this->deactivation_reason->value : $this->deactivation_reason,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
