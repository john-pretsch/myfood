<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class JepflowSsoClient
{
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
