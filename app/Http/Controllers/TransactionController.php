<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Transactions\ExportTransactionsCsvAction;
use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $kind = $request->query('kind') ?? $request->query('type');

        $perPage = min(100, max(1, $request->integer('per_page', 25)));

        $transactions = $user->transactions()
            ->with('account')
            ->ofKind($kind)
            ->forAccount($request->query('account_id'))
            ->forUserPlan($user->plan)
            ->activeOnly($request->boolean('active_only'))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->successResponse(
            TransactionResource::collection($transactions->items()),
            ApiMessage::TRANSACTIONS_FETCHED,
            HttpCode::OK,
            [
                'page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        );
    }

    public function export(Request $request, ExportTransactionsCsvAction $action): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->cannot('export', Transaction::class)) {
            return $this->errorResponse(
                ApiMessage::PREMIUM_REQUIRED,
                HttpCode::FORBIDDEN
            );
        }

        return $action->handle($user);
    }
}
