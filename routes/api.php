<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\ImageController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('collections', CollectionController::class)->except(['index', 'show']);
    Route::apiResource('albums', AlbumController::class)->except(['show']);
    Route::apiResource('images', ImageController::class)->except(['show']);
});

Route::get('collections', [CollectionController::class, 'index']);
Route::get('collections/{id}', [CollectionController::class, 'show']);
Route::get('albums', [AlbumController::class, 'index']);
Route::get('albums/{id}', [AlbumController::class, 'show']);
Route::get('images', [ImageController::class, 'index']);
Route::get('images/{id}', [ImageController::class, 'show']);
Route::get('test', function () {
    return ['message' => 'API is working'];
});
