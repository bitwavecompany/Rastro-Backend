<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sync\ProcessBatchSyncAction;
use App\Actions\Sync\ProcessSyncPullAction;
use App\Constants\ApiMessage;
use App\Http\Requests\SyncPushRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(SyncPushRequest $request, ProcessBatchSyncAction $action): JsonResponse
    {
        $results = $action->handle($request->user(), $request->toOperations());

        return $this->successResponse(['results' => $results], ApiMessage::BATCH_PROCESSED);
    }

    public function pull(Request $request, ProcessSyncPullAction $action): JsonResponse
    {
        $since = $request->query('since');
        $data = $action->handle($request->user(), is_string($since) ? $since : null);

        return $this->successResponse($data, ApiMessage::SYNC_PULL_SUCCESS);
    }
}
