<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Filament\AdminLoginController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/login', [AdminLoginController::class, 'login']);
