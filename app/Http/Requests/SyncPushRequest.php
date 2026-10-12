<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Sync\BatchOperationData;
use App\Enums\SyncEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operations' => ['required', 'array'],
            'operations.*.opId' => ['required', 'uuid'],
            'operations.*.entity' => ['required', Rule::enum(SyncEntity::class)],
            'operations.*.op' => ['required', 'string', 'in:create,update,delete,restore,hard-delete,hardDelete,force-delete,purge-account,purgeAccount,purge-all,purgeAll,relocate,transfer-record'],
            'operations.*.payload' => ['present', 'array'],
        ];
    }

    /**
     * @return BatchOperationData[]
     */
    public function toOperations(): array
    {
        $raw = $this->validated('operations', []);

        return array_map(
            static fn (array $opData): BatchOperationData => BatchOperationData::fromArray($opData),
            $raw
        );
    }
}
