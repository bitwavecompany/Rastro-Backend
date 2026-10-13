<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register-sync', [AuthController::class, 'registerSync']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
});

Route::get('/ping', function () {
    return response()->json(['status' => 'Rastro API V1 Operational']);
});

Route::middleware(['auth:sanctum', 'session.active'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/me', [AuthController::class, 'updateMe']);
    Route::delete('/auth/me', [AuthController::class, 'deleteAccount']);
    Route::post('/auth/upgrade-request', [AuthController::class, 'upgradeRequest']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/sync/push', [SyncController::class, 'push'])->middleware('throttle:60,1');
    Route::get('/sync/pull', [SyncController::class, 'pull'])->middleware('throttle:60,1');

    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/export', [TransactionController::class, 'export']);
    Route::get('/categories', [CategoryController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'session.active', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'index']);
    Route::patch('/users/{id}/plan', [AdminController::class, 'togglePlan']);
    Route::get('/premium-requests', [AdminController::class, 'premiumRequests']);
    Route::post('/premium-requests/{id}/approve', [AdminController::class, 'approveRequest']);
    Route::post('/premium-requests/{id}/reject', [AdminController::class, 'rejectRequest']);
    Route::post('/users/{id}/manual-upgrade', [AdminController::class, 'manualCashUpgrade']);
});
