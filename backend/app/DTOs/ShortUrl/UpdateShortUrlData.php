<?php

namespace App\DTOs\ShortUrl;

final readonly class UpdateShortUrlData
{
    public function __construct(
        public ?string $longUrl = null,
        public ?bool $isActive = null,
        public ?string $expiresAt = null,
        public bool $expiresAtProvided = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            longUrl: $data['long_url'] ?? null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            expiresAt: $data['expires_at'] ?? null,
            expiresAtProvided: array_key_exists('expires_at', $data),
        );
    }
}
