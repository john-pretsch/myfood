<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RecipeController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('user', [AuthController::class, 'me']);

    Route::post('recipes/import', [RecipeController::class, 'import'])->middleware('auth');
});

Route::apiResource('recipes', RecipeController::class);
