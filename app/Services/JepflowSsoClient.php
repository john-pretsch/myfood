<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class JepflowSsoClient
{
    public function passwordGrant(string $email, string $password): array
    {
        $response = Http::asForm()->post($this->url('/oauth/token'), [
            'grant_type' => 'password',
            'client_id' => config('services.jepflow_sso.client_id'),
            'client_secret' => config('services.jepflow_sso.client_secret'),
            'username' => $email,
            'password' => $password,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('SSO rejected the credentials.');
        }

        return $response->json();
    }

    public function user(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get($this->url('/api/user'))
            ->throw()
            ->json();
    }

    /**
     * @return array{global: ?string, apps: array<string, string>}
     */
    public function permissions(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get($this->url('/api/permissions'))
            ->throw()
            ->json();
    }

    private function url(string $path): string
    {
        return rtrim(config('services.jepflow_sso.base_url'), '/').$path;
    }
}
