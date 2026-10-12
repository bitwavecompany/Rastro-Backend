<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'user_id' => $this->user_id,
            'type' => $this->type?->value,
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'description' => $this->description,
            'payment_method' => $this->payment_method,
            'date' => $this->date?->toDateString(),
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'transfer_id' => $this->transfer_id,
            'deletion_reason' => $this->deletion_reason,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'account' => new AccountResource($this->whenLoaded('account')),
        ];
    }
}
