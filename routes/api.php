<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\ImageController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('albums', AlbumController::class);
Route::apiResource('collections', CollectionController::class);
Route::apiResource('images', ImageController::class);
Route::get('collections/{id}/albums', [CollectionController::class, 'albums']);
Route::get('collections/{collectionId}/albums/{albumId}', [CollectionController::class, 'albumInCollection']);
Route::get('albums/{albumId}/images', [AlbumController::class, 'images']);
