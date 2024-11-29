<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;


Route::post('signUp',[AuthController::class,'signUp']);
Route::post('logIn',[AuthController::class,'logIn']);
Route::post('logOut',[AuthController::class,'logOut'])->middleware('auth:sanctum');
Route::apiResource('products',ProductController::class);

