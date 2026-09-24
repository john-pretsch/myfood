<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JepflowSsoClient;
use App\Services\SsoUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request, JepflowSsoClient $sso, SsoUserResolver $resolver)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $token = $sso->passwordGrant($credentials['email'], $credentials['password']);
            $ssoUser = $sso->user($token['access_token']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $resolver->resolve($ssoUser);

        try {
            $permissions = $sso->permissions($token['access_token']);
            $resolver->syncRole($user, $permissions['apps']['myfood'] ?? null);
        } catch (Throwable $e) {
            // Permissions are non-critical to login; leave the user's existing role unchanged.
        }

        Auth::login($user, remember: $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($request)]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request)]);
    }

    private function userPayload(Request $request): ?array
    {
        $user = $request->user();

        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }
}
