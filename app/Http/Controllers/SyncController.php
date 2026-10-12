<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sync\ProcessBatchSyncAction;
use App\Constants\ApiMessage;
use App\Http\Requests\SyncPushRequest;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function push(SyncPushRequest $request, ProcessBatchSyncAction $action): JsonResponse
    {
        $results = $action->handle($request->user(), $request->toOperations());

        return $this->successResponse(['results' => $results], ApiMessage::BATCH_PROCESSED);
    }
}
