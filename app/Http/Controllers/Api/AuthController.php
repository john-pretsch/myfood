<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Sign-in and sign-out happen through Jepflow SSO (see Auth\SsoController);
 * this only reports who the current session belongs to.
 */
class AuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role] : null,
        ]);
    }
}
