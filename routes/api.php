<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// ✅ Публичные
Route::post('/login', [AuthController::class, 'login']);


Route::get('/products', [ProductController::class, 'index']);

/*
// ✅ Защищённые маршруты
Route::middleware('auth:api')->group(function () {

    Route::get('/products', [ProductController::class, 'index']);

});
*/
