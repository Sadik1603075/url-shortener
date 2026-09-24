<?php

namespace App\Repositories\Contracts;

use App\Models\ShortUrl;

interface ShortUrlRepositoryInterface
{
    public function findByShortCode(string $shortCode): ?ShortUrl;

    public function create(
        int $userId,
        string $shortCode,
        string $longUrl,
        ?string $expiresAt = null,
    ): ShortUrl;

    public function incrementClickCount(ShortUrl $shortUrl): void;
}