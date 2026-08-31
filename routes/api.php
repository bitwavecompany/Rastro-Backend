<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/auth/register-sync', [AuthController::class, 'registerSync']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/ping', function () {
    return response()->json(['status' => 'Rastro API V1 Operational']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/me', [AuthController::class, 'updateMe']);
    Route::post('/auth/upgrade-request', [AuthController::class, 'upgradeRequest']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    Route::apiResource('accounts', \App\Http\Controllers\AccountController::class);
    Route::apiResource('transactions', \App\Http\Controllers\TransactionController::class);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'index']);
    Route::patch('/users/{id}/plan', [\App\Http\Controllers\AdminController::class, 'togglePlan']);
});