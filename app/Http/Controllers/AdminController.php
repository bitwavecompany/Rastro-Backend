<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Admin\ApprovePremiumRequestAction;
use App\Actions\Admin\ManualCashUpgradeAction;
use App\Actions\Admin\RejectPremiumRequestAction;
use App\Actions\Admin\ToggleUserPlanAction;
use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\PremiumRequestStatus;
use App\Enums\UserRole;
use App\Http\Requests\AdminManualCashUpgradeRequest;
use App\Http\Requests\AdminRejectPremiumRequest;
use App\Http\Requests\AdminTogglePlanRequest;
use App\Http\Resources\PremiumRequestResource;
use App\Http\Resources\UserResource;
use App\Models\PremiumRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 25)));

        $users = User::where('role', UserRole::Client)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->successResponse(
            UserResource::collection($users->items()),
            ApiMessage::USERS_FETCHED,
            HttpCode::OK,
            [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        );
    }

    public function premiumRequests(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 25)));

        $requests = PremiumRequest::where('status', PremiumRequestStatus::PendingVerification)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->successResponse(
            PremiumRequestResource::collection($requests->items()),
            ApiMessage::PREMIUM_REQUESTS_FETCHED,
            HttpCode::OK,
            [
                'page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ]
        );
    }

    public function approveRequest(Request $request, string $id, ApprovePremiumRequestAction $action): JsonResponse
    {
        $premiumRequest = PremiumRequest::with('user')->findOrFail($id);

        $updated = $action->handle($premiumRequest, $request->user());

        return $this->successResponse(
            new PremiumRequestResource($updated),
            ApiMessage::PREMIUM_REQUEST_APPROVED
        );
    }

    public function rejectRequest(AdminRejectPremiumRequest $request, string $id, RejectPremiumRequestAction $action): JsonResponse
    {
        $premiumRequest = PremiumRequest::with('user')->findOrFail($id);

        $updated = $action->handle($premiumRequest, $request->toDto(), $request->user());

        return $this->successResponse(
            new PremiumRequestResource($updated),
            ApiMessage::PREMIUM_REQUEST_REJECTED
        );
    }

    public function manualCashUpgrade(AdminManualCashUpgradeRequest $request, string $id, ManualCashUpgradeAction $action): JsonResponse
    {
        $user = User::findOrFail($id);

        $updatedUser = $action->handle($user, $request->toDto(), $request->user());

        return $this->successResponse(
            new UserResource($updatedUser),
            ApiMessage::PLAN_UPDATED
        );
    }

    public function togglePlan(AdminTogglePlanRequest $request, string $id, ToggleUserPlanAction $action): JsonResponse
    {
        $user = User::findOrFail($id);

        $updatedUser = $action->handle($user, $request->toDto());

        return $this->successResponse(
            new UserResource($updatedUser),
            ApiMessage::PLAN_UPDATED
        );
    }
}
