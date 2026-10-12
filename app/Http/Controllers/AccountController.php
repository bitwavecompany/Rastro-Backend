<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\ApiMessage;
use App\Http\Resources\AccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->accounts()
            ->ofType($request->query('type'))
            ->activeOnly($request->boolean('active_only'))
            ->latest()
            ->get();

        return $this->successResponse(
            AccountResource::collection($accounts),
            ApiMessage::ACCOUNTS_FETCHED
        );
    }
}
