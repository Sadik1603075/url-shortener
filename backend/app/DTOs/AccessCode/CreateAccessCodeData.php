<?php

namespace App\DTOs\AccessCode;

readonly class CreateAccessCodeData
{
    public function __construct(
        public string  $email,
        public ?string $description = null,
        public ?string $expiresAt = null,
    ) {}
}
