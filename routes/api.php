<?php

use App\Http\Controllers\Admin\FacebookSettingController;
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
use App\Http\Controllers\API\CustomerLeaderboardController;
use App\Http\Controllers\API\DashboardSummaryController;
use App\Http\Controllers\API\PathaoController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\API\RolePermissionController;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::post('signUp', [AuthController::class, 'signUp']);
Route::post('logIn', [AuthController::class, 'logIn']);

// shop page routes
Route::get('/shop/products', [ProductController::class, 'shopProducts']);
Route::get('/shop/filters', [ProductController::class, 'shopFilters']);

// Products, Banners, Sizes, Categories (frontend only)
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::apiResource('banners', BannerController::class)->only(['index', 'show']);
Route::apiResource('sizes', SizeController::class)->only(['index', 'show']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::get('/product_add_category', [CategoryController::class, 'product_add_category']);

// products whos category is same
Route::get('/category_products/{id}', [ProductController::class, 'category_products']);

// product search
Route::get('/product-search', [ProductController::class, 'searchProducts']);

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

// Abandoned checkout system
Route::post('/track-abandoned-checkout', [AbandonedCheckoutController::class, 'store']);
Route::get('/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);
Route::post('/mark-checkout-converted', [AbandonedCheckoutController::class, 'markAsConverted']);

// Customer Leaderboard Routes
Route::prefix('customers')->group(function () {
    Route::get('/leaderboard', [CustomerLeaderboardController::class, 'index']);
    Route::get('/statistics', [CustomerLeaderboardController::class, 'statistics']);
    Route::get('/{phone}', [CustomerLeaderboardController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated Users)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Check auth status
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logOut', [AuthController::class, 'logOut']);
    // User's order history
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/my-orders/{orderNumber}', [OrderController::class, 'myOrderDetails']);
});

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    // Role & Permission Management
    Route::get('/roles', [RolePermissionController::class, 'getRoles']);
    Route::post('/roles', [RolePermissionController::class, 'createRole']);
    Route::put('/roles/{id}', [RolePermissionController::class, 'updateRole']);
    Route::delete('/roles/{id}', [RolePermissionController::class, 'deleteRole']);

    Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);

    // User Management
    Route::get('/users', [RolePermissionController::class, 'getUsers']);
    Route::post('/users', [RolePermissionController::class, 'createUser']);
    Route::delete('/users/{id}', [RolePermissionController::class, 'deleteUser']);
    Route::post('/users/{userId}/assign-role', [RolePermissionController::class, 'assignRole']);
    Route::post('/users/{userId}/remove-role', [RolePermissionController::class, 'removeRole']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Super Admin & Admin)
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Permission Protected Routes (Copy & Paste)
|--------------------------------------------------------------------------
*/

// --------------------------
// PRODUCTS PERMISSIONS
// --------------------------
// Route::middleware(['permission:view products'])
//     ->get('products', [ProductController::class, 'index']);

Route::middleware(['permission:create products'])
    ->post('products', [ProductController::class, 'store']);

Route::middleware(['permission:edit products'])
    ->put('products/{id}', [ProductController::class, 'update']);

Route::middleware(['permission:delete products'])
    ->delete('products/{id}', [ProductController::class, 'destroy']);


// --------------------------
// CATEGORIES PERMISSIONS
// --------------------------
Route::middleware(['permission:create categories'])
    ->post('categories', [CategoryController::class, 'store']);

Route::middleware(['permission:edit categories'])
    ->put('categories/{id}', [CategoryController::class, 'update']);

Route::middleware(['permission:delete categories'])
    ->delete('categories/{id}', [CategoryController::class, 'destroy']);


// --------------------------
// SIZES PERMISSIONS
// --------------------------
Route::middleware(['permission:create sizes'])
    ->post('sizes', [SizeController::class, 'store']);

Route::middleware(['permission:edit sizes'])
    ->put('sizes/{id}', [SizeController::class, 'update']);

Route::middleware(['permission:delete sizes'])
    ->delete('sizes/{id}', [SizeController::class, 'destroy']);


// --------------------------
// ORDERS PERMISSIONS
// --------------------------
Route::middleware(['permission:view orders'])
    ->get('orders', [OrderController::class, 'index']);

Route::middleware(['permission:edit orders'])
    ->put('orders/{id}', [OrderController::class, 'update']);
Route::middleware(['permission:order show'])
    ->get('orders/{id}', [OrderController::class, 'show']);

Route::middleware(['permission:delete orders'])
    ->delete('orders/{id}', [OrderController::class, 'destroy']);
// Order download csv
Route::middleware(['permission:download orders'])
    ->get('orders-download-csv', [OrderController::class, 'downloadCSV']);


// Order status update
Route::middleware(['permission:order_status'])
    ->post('order_status/{id}', [OrderController::class, 'order_status']);

// Incomplete order tracking 
Route::middleware(['permission:incomplete_order'])->get('/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);
Route::post('/track-abandoned-checkout', [AbandonedCheckoutController::class, 'store']);
// Route::get('/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);
Route::post('/mark-checkout-converted', [AbandonedCheckoutController::class, 'markAsConverted']);


// --------------------------
// PRODUCT VARIANTS PERMISSIONS
// --------------------------
Route::middleware(['permission:create product variants'])
    ->post('product-variants', [ProductVariantController::class, 'store']);

Route::middleware(['permission:edit product variants'])
    ->put('product-variants/{id}', [ProductVariantController::class, 'update']);

Route::middleware(['permission:delete product variants'])
    ->delete('product-variants/{id}', [ProductVariantController::class, 'destroy']);


// --------------------------
// INVENTORY PERMISSIONS
// --------------------------
Route::middleware(['permission:manage inventory'])
    ->apiResource('inventory-management', ProductStockController::class)
    ->only(['index', 'store', 'update', 'destroy']);


// --------------------------
// SHIPPING COSTS PERMISSIONS
// --------------------------
Route::middleware(['permission:manage shipping costs'])
    ->apiResource('shipping-costs', ShippingCostController::class);

Route::get('shipping-costs-latest', [ShippingCostController::class, 'latest']);


// --------------------------
// BANNERS PERMISSIONS
// --------------------------
Route::middleware(['permission:create banners'])
    ->post('banners', [BannerController::class, 'store']);

Route::middleware(['permission:edit banners'])
    ->put('banners/{id}', [BannerController::class, 'update']);

Route::middleware(['permission:delete banners'])
    ->delete('banners/{id}', [BannerController::class, 'destroy']);


// --------------------------
// SOCIAL LINKS PERMISSIONS
// --------------------------
Route::middleware(['permission:edit social links'])
    ->post('social-links', [SocialLinkController::class, 'store']);

Route::middleware(['permission:edit social links'])
    ->put('social-links/{id}', [SocialLinkController::class, 'update']);


// --------------------------
// ABOUT US PERMISSIONS
// --------------------------
Route::middleware(['permission:create about us'])
    ->post('about-us', [AboutUsController::class, 'store']);

Route::middleware(['permission:edit about us'])
    ->put('about-us/{id}', [AboutUsController::class, 'update']);

Route::middleware(['permission:delete about us'])
    ->delete('about-us/{id}', [AboutUsController::class, 'destroy']);


// --------------------------
// PATHAO PERMISSIONS
// --------------------------
Route::middleware(['permission:create pathao orders'])
    ->post('/pathao/orders/{orderId}/create', [PathaoController::class, 'createOrder']);


// --------------------------
// FACEBOOK TRACKING PERMISSIONS
// --------------------------
Route::middleware(['permission:view facebook settings'])
    ->get('/facebook-settings', [FacebookSettingController::class, 'index']);

Route::middleware(['permission:edit facebook settings'])
    ->post('/facebook-settings', [FacebookSettingController::class, 'store']);

Route::middleware(['permission:test facebook settings'])
    ->post('/facebook-settings/test', [FacebookSettingController::class, 'testConnection']);

// Customer Leaderboard Routes (Permission Protected Individually)
Route::prefix('customers')->group(function () {

    // Only users with 'view leaderboard' permission
    Route::get('/leaderboard', [CustomerLeaderboardController::class, 'index'])
        ->middleware(['auth:sanctum', 'permission:view leaderboard']);

    // Only users with 'view statistics' permission
    Route::get('/statistics', [CustomerLeaderboardController::class, 'statistics'])
        ->middleware(['auth:sanctum', 'permission:view statistics']);

    // Only users with 'view customer details' permission
    Route::get('/{phone}', [CustomerLeaderboardController::class, 'show'])
        ->middleware(['auth:sanctum', 'permission:view customer details']);
});


// Dashboard summary (Permission Protected)
Route::middleware(['auth:sanctum', 'permission:view dashboard summary'])
    ->get('/dashboard/summary', [DashboardSummaryController::class, 'summary']);

