<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Constants\HttpCode;
use App\DTOs\Sync\BatchOperationData;
use App\Factories\SyncCommandFactory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessBatchSyncAction
{
    /**
     * @param  BatchOperationData[]  $operations
     */
    public function handle(User $user, array $operations): array
    {
        $results = [];

        DB::beginTransaction();

        try {
            foreach ($operations as $operation) {
                try {
                    DB::transaction(function () use ($user, $operation): void {
                        $command = SyncCommandFactory::make($operation);
                        $command->execute($user);
                    });

                    $results[] = [
                        'opId' => $operation->opId,
                        'status' => 'success',
                    ];
                } catch (Throwable $e) {
                    $results[] = [
                        'opId' => $operation->opId,
                        'status' => 'error',
                        'code' => $e->getCode() ?: HttpCode::UNPROCESSABLE_ENTITY,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }
}
