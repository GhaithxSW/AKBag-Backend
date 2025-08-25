<?php

use App\Http\Controllers\Filament\AdminLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/login', [AdminLoginController::class, 'login']);
