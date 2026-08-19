<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Rastro Backend API',
        'status' => 'operational',
        'framework' => app()->version()
    ]);
});