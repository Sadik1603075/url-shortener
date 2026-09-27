<?php

namespace App\Events;

use Carbon\CarbonImmutable;

/**
 * Domain event: a short URL was resolved and the visitor was redirected.
 *
 * Immutable fact. Serialized to JSON for the Kafka `url.clicked` topic and
 * projected into the analytics read model by the ClickProjector.
 */
final readonly class UrlClicked
{
    public function __construct(
        public string $shortCode,
        public string $longUrl,
        public string $occurredAt,      // ISO-8601
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $referer = null,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'short_code' => $this->shortCode,
            'long_url' => $this->longUrl,
            'occurred_at' => $this->occurredAt,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            shortCode: (string) ($data['short_code'] ?? ''),
            longUrl: (string) ($data['long_url'] ?? ''),
            occurredAt: (string) ($data['occurred_at'] ?? CarbonImmutable::now()->toIso8601String()),
            ip: $data['ip'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            referer: $data['referer'] ?? null,
        );
    }
}
