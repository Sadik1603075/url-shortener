<?php

namespace App\Services\ShortUrl;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\DTOs\Analytics\ClickContext;
use App\Events\UrlClicked;
use App\Messaging\Contracts\ClickEventPublisherInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class UrlRedirectService
{
    public function __construct(
        private readonly ShortUrlCacheInterface        $cache,
        private readonly ShortUrlRepositoryInterface   $repository,
        private readonly ClickEventPublisherInterface  $publisher,
    ) {}

    public function redirect(string $shortCode, ClickContext $context): RedirectResponse
    {
        $cachedUrl = $this->cache->get($shortCode);

        if ($cachedUrl !== null) {
            $this->emitClick($shortCode, $cachedUrl, $context);

            return redirect()->away($cachedUrl);
        }

        $shortUrl = $this->repository->findByShortCode($shortCode);

        if (! $shortUrl || ! $shortUrl->isValid()) {
            abort(404);
        }

        $this->cache->put(
            $shortCode,
            $shortUrl->long_url,
            $this->calculateTtl($shortUrl->expires_at),
        );

        $this->emitClick($shortCode, $shortUrl->long_url, $context);

        return redirect()->away($shortUrl->long_url);
    }

    /**
     * Fire-and-forget click emission. The redirect must never fail because the
     * analytics transport (Kafka / DB) is unavailable — swallow and log.
     */
    private function emitClick(string $shortCode, string $longUrl, ClickContext $context): void
    {
        try {
            $this->publisher->publish(new UrlClicked(
                shortCode:  $shortCode,
                longUrl:    $longUrl,
                occurredAt: CarbonImmutable::now()->toIso8601String(),
                ip:         $context->ip,
                userAgent:  $context->userAgent,
                referer:    $context->referer,
            ));
        } catch (\Throwable $e) {
            Log::warning('click.publish.failed', [
                'short_code' => $shortCode,
                'error'      => $e->getMessage(),
            ]);
        }
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
