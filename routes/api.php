<?php

use App\Http\Controllers\Api\RecipeController;
use Illuminate\Support\Facades\Route;

Route::post('recipes/import', [RecipeController::class, 'import']);
Route::apiResource('recipes', RecipeController::class);
