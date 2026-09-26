<?php

namespace App\DTOs\AccessCode;

readonly class UpdateAccessCodeData
{
    public function __construct(
        public ?string $description = null,
        public ?bool   $isActive = null,
        public ?string $expiresAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? null,
            isActive:    $data['is_active'] ?? null,
            expiresAt:   array_key_exists('expires_at', $data) ? $data['expires_at'] : null,
        );
    }
}
