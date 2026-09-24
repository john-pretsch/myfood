<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('logout', [SsoController::class, 'logout'])->middleware('auth');
    Route::get('user', [AuthController::class, 'me']);

    Route::post('recipes/import', [RecipeController::class, 'import'])->middleware('auth');
    Route::get('tags', [TagController::class, 'index']);
    Route::post('tags', [TagController::class, 'store'])->middleware(['auth', 'can:admin']);
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->middleware(['auth', 'can:admin']);

    Route::post('recipes/{recipe}/image', [RecipeController::class, 'updateImage'])->middleware('auth');

    Route::apiResource('recipes', RecipeController::class)->except(['index', 'show'])->middleware('auth');
});

Route::apiResource('recipes', RecipeController::class)->only(['index', 'show']);
