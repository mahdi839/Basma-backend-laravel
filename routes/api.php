<?php

use App\Http\Controllers\API\AbandonedCheckoutController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SizeController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\BannerController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductsSlotController;
use App\Http\Controllers\API\ShippingCostController;
use App\Http\Controllers\API\FooterSettingController;
use App\Http\Controllers\API\SocialLinkController;
use App\Http\Controllers\API\AboutUsController;
use App\Http\Controllers\API\DashboardSummaryController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\ProductStockController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/



// Auth
Route::post('signUp', [AuthController::class, 'signUp']);
Route::post('logIn', [AuthController::class, 'logIn']);

// Products, Banners, Sizes, Categories (frontend only)
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::apiResource('banners', BannerController::class)->only(['index', 'show']);
Route::apiResource('sizes', SizeController::class)->only(['index', 'show']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);

// Frontend-specific endpoints
Route::get('frontend/categories', [CategoryController::class, 'frontEndIndex']);
Route::get('frontend/banner', [BannerController::class, 'frontEndIndex']);
Route::get('product-slots_index/frontEndIndex', [ProductsSlotController::class, 'frontEndIndex']);

// Orders (frontend store)
Route::apiResource('orders', OrderController::class)->only(['store']);

// Footer & Social links (public fetch)
Route::apiResource('footer-settings', FooterSettingController::class);
Route::get('social-links-first', [SocialLinkController::class, 'getFirst']);

// About Us (frontend fetch)
Route::get('about-us', [AboutUsController::class, 'index']);

 // Variants Crud
    Route::apiResource('product-variants', ProductVariantController::class)->only(['index', 'show']);

//  Abandoned checkout system
    Route::post('/track-abandoned-checkout', [AbandonedCheckoutController::class, 'store']);
    Route::get('/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);
// In routes/api.php
    Route::post('/mark-checkout-converted', [AbandonedCheckoutController::class, 'markAsConverted']);

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
   
//    dashboard summery
    Route::get('/dashboard/summary', [DashboardSummaryController::class, 'summary']);
    
    // Auth
    Route::post('logOut', [AuthController::class, 'logOut']);

    // Products, Sizes, Categories CRUD
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('sizes', SizeController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);

    // Shipping Costs
    Route::apiResource('shipping-costs', ShippingCostController::class);
    Route::get('shipping-costs-latest', [ShippingCostController::class, 'latest']);

    // Product Slots
    Route::apiResource('product-slots', ProductsSlotController::class);
    Route::get('slots_products/create', [ProductsSlotController::class, 'create']);
    Route::get('product-slots/edit/{id}', [ProductsSlotController::class, 'edit']);

    // Orders CRUD
    Route::apiResource('orders', OrderController::class)->only(['index', 'create', 'update', 'destroy', 'show']);
    Route::post('order_status/{id}', [OrderController::class, 'order_status']);

    //  show incomplete orders
    Route::get('/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);

    // order download csv
    Route::get('orders-download-csv', [OrderController::class, 'downloadCSV']);

    // Banners CRUD
    Route::apiResource('banners', BannerController::class)->only(['store', 'update', 'destroy']);

    // Social Links
    Route::apiResource('social-links', SocialLinkController::class)->only(['store', 'update']);

    // About Us CRUD
    Route::post('about-us', [AboutUsController::class, 'store']);
    Route::put('about-us/{id}', [AboutUsController::class, 'update']);
    Route::delete('about-us/{id}', [AboutUsController::class, 'destroy']);

    // Variants Crud
    Route::apiResource('product-variants', ProductVariantController::class)->only(['store', 'update','destroy']);

    //    inventory management
    Route::apiResource('inventory-management', ProductStockController::class);
});
  