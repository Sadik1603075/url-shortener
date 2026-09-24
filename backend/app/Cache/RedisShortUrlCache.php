<?php

namespace App\Cache;

use App\Cache\Contracts\ShortUrlCacheInterface;
use Illuminate\Support\Facades\Redis;

class RedisShortUrlCache implements ShortUrlCacheInterface
{
    private const PREFIX = 'short_url:';

    public function get(string $shortCode): ?string
    {
        return Redis::get(
            self::PREFIX . $shortCode
        );
    }

    public function put(
        string $shortCode,
        string $longUrl,
        ?int $ttl = null,
    ): void {
        $key = self::PREFIX . $shortCode;

        if ($ttl !== null) {
            Redis::setex($key, $ttl, $longUrl);
            return;
        }

        Redis::set($key, $longUrl);
    }

    public function forget(string $shortCode): void
    {
        Redis::del(
            self::PREFIX . $shortCode
        );
    }
}