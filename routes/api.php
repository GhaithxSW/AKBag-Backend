<?php

use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\FeaturedImageController;
use App\Http\Controllers\Api\ImageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->post('logout', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Public read-only routes
Route::get('albums', [AlbumController::class, 'index']);
Route::get('albums/{album}', [AlbumController::class, 'show']);
Route::get('collections', [CollectionController::class, 'index']);
Route::get('collections/{collection}', [CollectionController::class, 'show']);
Route::get('images', [ImageController::class, 'index']);
Route::get('images/{image}', [ImageController::class, 'show']);
Route::get('featured-images', [FeaturedImageController::class, 'index']);
Route::get('featured-images/{featured_image}', [FeaturedImageController::class, 'show']);

Route::get('collections/{id}/albums', [CollectionController::class, 'albums']);
Route::get('collections/{collectionId}/albums/{albumId}', [CollectionController::class, 'albumInCollection']);
Route::get('albums/{albumId}/images', [AlbumController::class, 'images']);

// Protected routes requiring authentication and admin privileges
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('albums', [AlbumController::class, 'store']);
    Route::put('albums/{album}', [AlbumController::class, 'update']);
    Route::patch('albums/{album}', [AlbumController::class, 'update']);
    Route::delete('albums/{album}', [AlbumController::class, 'destroy']);

    Route::post('collections', [CollectionController::class, 'store']);
    Route::put('collections/{collection}', [CollectionController::class, 'update']);
    Route::patch('collections/{collection}', [CollectionController::class, 'update']);
    Route::delete('collections/{collection}', [CollectionController::class, 'destroy']);

    Route::post('images', [ImageController::class, 'store']);
    Route::put('images/{image}', [ImageController::class, 'update']);
    Route::patch('images/{image}', [ImageController::class, 'update']);
    Route::delete('images/{image}', [ImageController::class, 'destroy']);

    Route::post('featured-images', [FeaturedImageController::class, 'store']);
    Route::put('featured-images/{featured_image}', [FeaturedImageController::class, 'update']);
    Route::patch('featured-images/{featured_image}', [FeaturedImageController::class, 'update']);
    Route::delete('featured-images/{featured_image}', [FeaturedImageController::class, 'destroy']);
});
