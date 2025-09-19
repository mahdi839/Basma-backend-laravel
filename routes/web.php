<?php

use App\Http\Controllers\web\DashboardController\HomeController;
use Illuminate\Support\Facades\Route;


Route::get('/',[HomeController::class,'index']);
