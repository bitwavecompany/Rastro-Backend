<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::Admin) {
            return response()->json([
                'success' => false,
                'message' => ApiMessage::FORBIDDEN,
                'data' => null,
            ], HttpCode::FORBIDDEN);
        }

        return $next($request);
    }
}
