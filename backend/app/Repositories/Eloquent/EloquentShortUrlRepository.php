<?php

namespace App\Repositories\Eloquent;

use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;

class EloquentShortUrlRepository implements ShortUrlRepositoryInterface
{
    public function findByShortCode(string $shortCode): ?ShortUrl
    {
        return ShortUrl::query()
            ->where('short_code', $shortCode)
            ->where('is_active', true)
            ->first();
    }

    public function create(
        int $userId,
        string $shortCode,
        string $longUrl,
        ?string $expiresAt = null,
    ): ShortUrl {
        return ShortUrl::create([
            'user_id' => $userId,
            'short_code' => $shortCode,
            'long_url' => $longUrl,
            'expires_at' => $expiresAt,
        ]);
    }

    public function incrementClickCount(ShortUrl $shortUrl): void
    {
        $shortUrl->increment('click_count');

        $shortUrl->update([
            'last_accessed_at' => now(),
        ]);
    }
}