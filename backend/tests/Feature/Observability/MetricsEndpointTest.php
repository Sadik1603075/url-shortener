<?php

namespace Tests\Feature\Observability;

use App\Messaging\Contracts\ClickEventPublisherInterface;
use App\Messaging\LogClickEventPublisher;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BindsInMemoryShortUrlCache;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use BindsInMemoryShortUrlCache;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // In-memory cache so redirects never touch Redis; Log publisher so the
        // click path doesn't run the DB projection.
        $this->bindInMemoryShortUrlCache();
        $this->app->bind(ClickEventPublisherInterface::class, LogClickEventPublisher::class);
    }

    public function test_metrics_endpoint_serves_prometheus_exposition(): void
    {
        // Warm the registry: the request's own metrics are recorded after the
        // response renders, so scrape a second time to see http_requests_total.
        $this->get('/metrics');

        $response = $this->get('/metrics')->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('linkforge_http_requests_total', $body);
        // Labelled by route PATTERN, not the concrete path (bounded cardinality).
        $this->assertStringContainsString('route="metrics"', $body);
    }

    public function test_redirect_increments_the_redirect_counter(): void
    {
        $user = User::factory()->create();
        ShortUrl::create([
            'user_id' => $user->id,
            'short_code' => 'metrics1',
            'long_url' => 'https://example.com',
        ]);

        $this->get('/metrics1')->assertRedirect('https://example.com');

        $body = $this->get('/metrics')->assertOk()->getContent();

        // Anchored so "…_total 1" doesn't also match "…_total 10".
        $this->assertMatchesRegularExpression('/^linkforge_redirect_total 1$/m', $body);
        // First hit for this code is a cache miss (then it's cached).
        $this->assertMatchesRegularExpression('/^linkforge_cache_events_total\{result="miss"\} 1$/m', $body);
    }
}
