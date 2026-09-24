<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JepflowSsoClient;
use App\Services\SsoUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SsoController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('jepflow_sso')->redirect();
    }

    public function callback(SsoUserResolver $resolver, JepflowSsoClient $sso): RedirectResponse
    {
        $ssoUser = Socialite::driver('jepflow_sso')->user();

        $user = $resolver->resolve([
            'id' => $ssoUser->id,
            'name' => $ssoUser->name,
            'email' => $ssoUser->email,
        ]);

        try {
            $permissions = $sso->permissions($ssoUser->token);
            $resolver->syncRole($user, $permissions['apps']['myfood'] ?? null);
        } catch (Throwable $e) {
            // Permissions are non-critical to login; leave the user's existing role unchanged.
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect('/');
    }
}
