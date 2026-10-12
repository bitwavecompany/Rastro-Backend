<?php

declare(strict_types=1);

namespace App\DTOs\Sync;

use App\Enums\SyncEntity;
use App\Enums\SyncOperation;

final readonly class BatchOperationData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $opId,
        public SyncEntity $entity,
        public SyncOperation $op,
        public array $payload
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $entity = $data['entity'] instanceof SyncEntity
            ? $data['entity']
            : SyncEntity::from((string) $data['entity']);

        $op = $data['op'] instanceof SyncOperation
            ? $data['op']
            : (SyncOperation::tryFromAlias((string) $data['op']) ?? SyncOperation::from((string) $data['op']));

        return new self(
            opId: (string) $data['opId'],
            entity: $entity,
            op: $op,
            payload: (array) ($data['payload'] ?? [])
        );
    }
}
