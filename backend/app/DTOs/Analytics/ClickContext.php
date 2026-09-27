<?php

namespace App\DTOs\Analytics;

use Illuminate\Http\Request;

final readonly class ClickContext
{
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $referer = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            referer: $request->headers->get('referer'),
        );
    }
}
