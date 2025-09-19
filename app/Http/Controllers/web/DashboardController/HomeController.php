<?php

namespace App\Http\Controllers\web\DashboardController;

use Illuminate\Http\Request;

class HomeController
{
    public function index (){
        return view('layouts.dashboard.layout');
    }
}
