<?php

namespace App\DTOs\ShortUrl;

final readonly class CreateShortUrlData
{
    public function __construct(
        public string $longUrl,
        public ?string $expiresAt = null,
    ) {}
}