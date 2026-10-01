<?php

namespace App\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Throwable;

/**
 * Thin, resilient wrapper over the Prometheus client (ADR-0004).
 *
 * Every record call is swallowed on failure: metrics must NEVER break a request
 * — especially the redirect hot path. Collectors are registered lazily.
 */
class Metrics
{
    private const HTTP_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5];

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly string $namespace = 'linkforge',
        private readonly bool $enabled = true,
    ) {}

    public function observeHttpRequest(string $method, string $route, int $status, float $seconds): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $this->registry
                ->getOrRegisterCounter($this->namespace, 'http_requests_total', 'Total HTTP requests', ['method', 'route', 'status'])
                ->inc([$method, $route, (string) $status]);

            $this->registry
                ->getOrRegisterHistogram($this->namespace, 'http_request_duration_seconds', 'HTTP request duration in seconds', ['method', 'route'], self::HTTP_BUCKETS)
                ->observe($seconds, [$method, $route]);
        } catch (Throwable) {
            // never let metrics break a request
        }
    }

    public function incRedirect(): void
    {
        $this->increment('redirect_total', 'Total short-URL redirects served');
    }

    public function incCacheEvent(string $result): void
    {
        $this->increment('cache_events_total', 'Short-URL cache lookups by result', ['result'], [$result]);
    }

    public function incClickPublished(): void
    {
        $this->increment('clicks_published_total', 'Click events published to the transport');
    }

    public function incClickPublishFailure(): void
    {
        $this->increment('click_publish_failures_total', 'Click event publish failures (degraded)');
    }

    public function incClickConsumed(): void
    {
        $this->increment('clicks_consumed_total', 'Click events consumed and projected');
    }

    public function render(): string
    {
        if (! $this->enabled) {
            return '';
        }

        try {
            return (new RenderTextFormat)->render($this->registry->getMetricFamilySamples());
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  list<string>  $labelNames
     * @param  list<string>  $labelValues
     */
    private function increment(string $name, string $help, array $labelNames = [], array $labelValues = []): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $this->registry
                ->getOrRegisterCounter($this->namespace, $name, $help, $labelNames)
                ->inc($labelValues);
        } catch (Throwable) {
            // swallow — metrics are best-effort
        }
    }
}
