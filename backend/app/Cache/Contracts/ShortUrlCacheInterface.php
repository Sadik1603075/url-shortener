<?php

namespace App\Cache\Contracts;

interface ShortUrlCacheInterface
{
    public function get(string $shortCode): ?string;

    public function put(
        string $shortCode,
        string $longUrl,
        ?int $ttl = null,
    ): void;

    public function forget(string $shortCode): void;
}