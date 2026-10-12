<?php

declare(strict_types=1);

namespace App\Traits;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function successResponse(mixed $data, string $message = ApiMessage::SUCCESS, int $code = HttpCode::OK, ?array $meta = null): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    protected function createdResponse(mixed $data, string $message = ApiMessage::CREATED): JsonResponse
    {
        return $this->successResponse($data, $message, HttpCode::CREATED);
    }

    protected function errorResponse(
        string $message,
        int $code = HttpCode::BAD_REQUEST,
        ?array $meta = null,
        ErrorCode|string|null $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($errorCode !== null) {
            $response['code'] = $errorCode instanceof ErrorCode ? $errorCode->value : $errorCode;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }
}
