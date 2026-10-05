<?php

namespace App\Application\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PrivateReadCache
{
    public function remember(int $owner, string $resource, array $filters, Closure $read): mixed
    {
        $version = Cache::remember("private:$owner:$resource:version", 3600, fn () => (string) Str::uuid());
        $key = "private:$owner:$resource:$version:".hash('sha256', json_encode($filters));
        // Private server-side storage, encrypted at rest; owner is always obtained from auth.
        $encrypted = Cache::remember($key, 60, fn () => Crypt::encrypt($read()));

        return Crypt::decrypt($encrypted);
    }

    public function forget(int $owner, string $resource): void
    {
        Cache::put("private:$owner:$resource:version", (string) Str::uuid(), 3600);
    }
}
