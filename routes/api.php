<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExpenseController;

Route::get('/gastos', [ExpenseController::class, 'index']);

Route::post('/gastos', [ExpenseController::class, 'store']);

Route::get('/ping', function () {
    return response()->json([
        'estado' => '¡Render por fin se actualizó!',
        'datos' => \App\Models\Expense::all()
    ]);
});