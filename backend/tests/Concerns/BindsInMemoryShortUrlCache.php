<?php

namespace Tests\Concerns;

use App\Cache\Contracts\ShortUrlCacheInterface;

/**
 * Binds an in-memory ShortUrlCacheInterface so redirect tests never touch Redis.
 */
trait BindsInMemoryShortUrlCache
{
    protected function bindInMemoryShortUrlCache(): void
    {
        $this->app->singleton(ShortUrlCacheInterface::class, fn () => new class implements ShortUrlCacheInterface
        {
            private array $store = [];

            public function get(string $shortCode): ?string
            {
                return $this->store[$shortCode] ?? null;
            }

            public function put(string $shortCode, string $longUrl, ?int $ttl = null): void
            {
                $this->store[$shortCode] = $longUrl;
            }

            public function forget(string $shortCode): void
            {
                unset($this->store[$shortCode]);
            }
        });
    }
}
