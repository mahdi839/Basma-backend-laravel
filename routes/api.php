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
    Route::post('/users/{userId}/assign-role', [RolePermissionController::class, 'assignRole']);
    Route::post('/users/{userId}/remove-role', [RolePermissionController::class, 'removeRole']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Super Admin & Admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super-admin|admin'])->group(function () {
    // Dashboard summary
    Route::get('/dashboard/summary', [DashboardSummaryController::class, 'summary']);

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

    // Show incomplete orders
    Route::get('/admin/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);

    // Order download csv
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
    Route::apiResource('product-variants', ProductVariantController::class)->only(['store', 'update', 'destroy']);

    // Inventory management
    Route::apiResource('inventory-management', ProductStockController::class);

    // Create Pathao order for a specific order
    Route::post('/pathao/orders/{orderId}/create', [PathaoController::class, 'createOrder']);

    // Facebook server side tracking
    Route::get('/facebook-settings', [FacebookSettingController::class, 'index']);
    Route::post('/facebook-settings', [FacebookSettingController::class, 'store']);
    Route::post('/facebook-settings/test', [FacebookSettingController::class, 'testConnection']);
});

Route::get('/test-token', [PathaoController::class, 'testToken']);