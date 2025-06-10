<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\ShippingCostController;


Route::post('signUp', [AuthController::class, 'signUp']);
Route::post('logIn', [AuthController::class, 'logIn']);
Route::post('logOut', [AuthController::class, 'logOut'])->middleware('auth:sanctum');

// Public routes (no auth needed)
Route::apiResource('products', ProductController::class)
    ->only(['index', 'show']);

Route::apiResource('sizes', SizeController::class)
    ->only(['index', 'show']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
// Protected admin-only routes
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    Route::apiResource('products', ProductController::class)
        ->only(['store', 'update', 'destroy']);

    Route::apiResource('sizes', SizeController::class)
        ->only(['store', 'update', 'destroy']);
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    Route::resource('shipping-costs', ShippingCostController::class);
});
