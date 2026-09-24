<?php

namespace App\Services\ShortUrl;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use Illuminate\Http\RedirectResponse;

class UrlRedirectService
{
    public function __construct(
        private readonly ShortUrlCacheInterface $cache,
        private readonly ShortUrlRepositoryInterface $repository,
    ) {}

    public function redirect(string $shortCode): RedirectResponse
    {
        $cachedUrl = $this->cache->get($shortCode);

        if ($cachedUrl !== null) {
            return redirect()->away($cachedUrl);
        }

        $shortUrl = $this->repository->findByShortCode($shortCode);

        if (!$shortUrl || !$shortUrl->isValid()) {
            abort(404);
        }

        $this->cache->put(
            $shortCode,
            $shortUrl->long_url,
            $this->calculateTtl($shortUrl->expires_at),
        );

        $this->repository->incrementClickCount($shortUrl);

        return redirect()->away($shortUrl->long_url);
    }

    private function calculateTtl($expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        $ttl = now()->diffInSeconds($expiresAt, false);

        return $ttl > 0 ? $ttl : 1;
    }
}