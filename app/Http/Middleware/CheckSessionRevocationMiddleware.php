<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\ErrorCode;
use App\Models\UserSession;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionRevocationMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                /** @var UserSession|null $session */
                $session = UserSession::where('access_token_id', $token->id)->first();

                if ($session && $session->revoked_at !== null) {
                    return $this->errorResponse(
                        message: ApiMessage::SESSION_REVOKED_REMOTE,
                        code: HttpCode::UNAUTHORIZED,
                        errorCode: ErrorCode::SessionRevokedRemote
                    );
                }
            }
        }

        return $next($request);
    }
}
