<?php

namespace App\Services;

use App\Models\User;

class SsoUserResolver
{
    public function resolve(array $ssoUser): User
    {
        $user = User::where('sso_id', $ssoUser['id'])->first();

        if (! $user) {
            $user = User::where('email', $ssoUser['email'])->first();

            if ($user) {
                $user->update(['sso_id' => $ssoUser['id']]);
            }
        }

        if (! $user) {
            $user = User::create([
                'name' => $ssoUser['name'],
                'email' => $ssoUser['email'],
                'sso_id' => $ssoUser['id'],
                'password' => null,
                'email_verified_at' => now(),
            ]);
        }

        return $user;
    }

    /**
     * Update the user's cached myfood role from an SSO permissions response.
     * Only call this when the permissions fetch succeeded, so a fetch
     * failure never wipes out a previously known role.
     */
    public function syncRole(User $user, ?string $role): void
    {
        $user->update(['role' => $role]);
    }
}
