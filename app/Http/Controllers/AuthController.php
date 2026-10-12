<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Auth\DeleteUserAccountAction;
use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RegisterAndSyncUserAction;
use App\Actions\Auth\RequestPremiumUpgradeAction;
use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\ErrorCode;
use App\Exceptions\SessionLimitReachedException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RegisterSyncRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpgradePremiumRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\UserResource;
use App\Services\Auth\TokenService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function registerSync(RegisterSyncRequest $request, RegisterAndSyncUserAction $action): JsonResponse
    {
        $result = $action->handle($request->toDto());

        return $this->createdResponse([
            'token' => $result['token'],
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'user' => new UserResource($result['user']),
            'syncedData' => [
                'accounts' => AccountResource::collection($result['syncedData']['accounts']),
                'transactions' => TransactionResource::collection($result['syncedData']['transactions']),
            ],
        ], ApiMessage::SUCCESS);
    }

    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        try {
            $result = $action->handle($request->toDto(), $request->ip());

            return $this->successResponse([
                'token' => $result['tokenData']['token'],
                'access_token' => $result['tokenData']['access_token'],
                'refresh_token' => $result['tokenData']['refresh_token'],
                'token_type' => $result['tokenData']['token_type'],
                'expires_in' => $result['tokenData']['expires_in'],
                'user' => new UserResource($result['user']),
            ], ApiMessage::SUCCESS);
        } catch (SessionLimitReachedException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                HttpCode::CONFLICT,
                null,
                ErrorCode::SessionLimitReached
            );
        }
    }

    public function refresh(RefreshTokenRequest $request, TokenService $tokenService): JsonResponse
    {
        try {
            $result = $tokenService->refreshToken(
                $request->toDto()->refreshToken,
                $request->ip()
            );

            return $this->successResponse([
                'token' => $result['token'],
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
                'user' => new UserResource($result['user']),
            ], ApiMessage::SUCCESS);
        } catch (Exception $e) {
            $code = $e->getCode() ?: HttpCode::UNAUTHORIZED;
            $errorCode = ($e->getMessage() === ApiMessage::SESSION_REVOKED_REMOTE)
                ? ErrorCode::SessionRevokedRemote
                : ErrorCode::InvalidRefreshToken;

            return $this->errorResponse(
                $e->getMessage(),
                $code,
                null,
                $errorCode
            );
        }
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()), ApiMessage::SUCCESS);
    }

    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $dto = $request->toDto();
        $user->update([
            'name' => $dto->name,
        ]);

        return $this->successResponse(new UserResource($user), ApiMessage::SUCCESS);
    }

    public function upgradeRequest(UpgradePremiumRequest $request, RequestPremiumUpgradeAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->toDto());

        return $this->successResponse(
            new UserResource($request->user()->fresh()),
            ApiMessage::PREMIUM_REQUEST_SUBMITTED
        );
    }

    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        $action->handle($request->user());

        return $this->successResponse(null, ApiMessage::SUCCESS);
    }

    public function deleteAccount(Request $request, DeleteUserAccountAction $action): JsonResponse
    {
        $action->handle($request->user());

        return $this->successResponse(null, ApiMessage::SUCCESS);
    }
}
