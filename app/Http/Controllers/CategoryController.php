<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\TransactionCategory;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->successResponse(
            TransactionCategory::toCatalog(),
            ApiMessage::CATEGORIES_FETCHED,
            HttpCode::OK
        );
    }
}
