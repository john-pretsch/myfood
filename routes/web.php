<?php

use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/sso/redirect', [SsoController::class, 'redirect']);
Route::get('/auth/sso/callback', [SsoController::class, 'callback']);

Route::view('/{any}', 'app')->where('any', '.*');
