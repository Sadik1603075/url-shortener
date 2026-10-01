<?php

namespace App\DTOs\AccessCode;

readonly class UpdateAccessCodeData
{
    public function __construct(
        public ?string $description = null,
        public ?bool $isActive = null,
        public ?string $expiresAt = null,
        // Whether `expires_at` was present in the payload. Distinguishes "field
        // omitted" (leave unchanged) from "field sent as null" (clear the expiry).
        public bool $expiresAtProvided = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'] ?? null,
            isActive: $data['is_active'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            expiresAtProvided: array_key_exists('expires_at', $data),
        );
    }
}
