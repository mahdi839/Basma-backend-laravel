<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductsSlotController;
use App\Http\Controllers\ShippingCostController;
use App\Http\Controllers\FooterSettingController;
use App\Http\Controllers\SocialLinkController;
Route::post('signUp', [AuthController::class, 'signUp']);
Route::post('logIn', [AuthController::class, 'logIn']);
Route::post('logOut', [AuthController::class, 'logOut'])->middleware('auth:sanctum');



Route::apiResource('products', ProductController::class)
    ->only(['index', 'show']);
Route::apiResource('banners', BannerController::class)->only(['index', 'show']);
Route::apiResource('sizes', SizeController::class)
    ->only(['index', 'show']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::get('frontend/categories', [CategoryController::class, 'frontEndIndex']);
Route::apiResource('orders', OrderController::class)->only(['store']);
Route::get('product-slots_index/frontEndIndex', [ProductsSlotController::class, 'frontEndIndex']);
// Protected admin-only routes
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    Route::apiResource('products', ProductController::class)
        ->only(['store', 'update', 'destroy']);

    Route::apiResource('sizes', SizeController::class)
        ->only(['store', 'update', 'destroy']);
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('shipping-costs', ShippingCostController::class);
    Route::get('shipping-costs-latest', [ShippingCostController::class, 'latest']);
    Route::apiResource('product-slots', ProductsSlotController::class);
    Route::get('slots_products/create', [ProductsSlotController::class, 'create']);
    Route::get('product-slots/edit/{id}', [ProductsSlotController::class, 'edit']);
    Route::apiResource('orders', OrderController::class)->only(['index','update','destroy','show']);
    Route::apiResource('banners', BannerController::class)->only(['store', 'update', 'destroy']);
    
    Route::apiResource('social-links', SocialLinkController::class)->only([
     'store', 'update'
   ]);
  
});
Route::get('social-links-first',[SocialLinkController::class,'getFirst']);
Route::apiResource('footer-settings', FooterSettingController::class);

