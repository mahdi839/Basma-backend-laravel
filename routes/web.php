<?php

use App\Http\Controllers\web\DashboardController\HomeController;
use App\Http\Controllers\web\DashboardController\SizeController;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/',[HomeController::class,'index']);
// Route::get('/admin/index/size',[SizeController::class,'index'])->name('admin.index.size');
// Route::get('/admin/create/size',[SizeController::class,'create'])->name('admin.create.size');
// Route::post('/admin/store/size',[SizeController::class,'store'])->name('admin.store.size');
// Route::get('/admin/edit/size/{id}',[SizeController::class,'edit'])->name('admin.edit.size');
// Route::put('/admin/update/size/{id}',[SizeController::class,'update'])->name('admin.update.size');
// Route::delete('/admin/delete/size/{id}',[SizeController::class,'destroy'])->name('admin.delete.size');

